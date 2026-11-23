<?php

namespace App\Filament\Pages\Reports;

use App\Enums\SupportIncentiveCategory;
use App\Enums\SupportIncentiveStatus;
use App\Models\Reporting;
use App\Models\SupportIncentive;
use App\Models\Team;
use App\Models\User;
use App\Exports\SupportIncentiveExport;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class SupportIncentiveReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.pages.reports.support-incentive-report';

    protected static ?string $title = 'Insentif Support';

    protected static ?string $navigationLabel = 'Insentif Support';

    protected static string|\UnitEnum|null $navigationGroup = 'Support Reports';

    protected static ?int $navigationSort = 17;

    public function getSubheading(): ?string
    {
        return 'Support mengajukan insentif dengan memilih reporting dan Form Support. Helpdesk memeriksa lalu menyetujui atau menolak pengajuan.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SupportIncentive::query()->with([
                'supportUser.team',
                'reporting.outstanding.location',
                'verifier',
            ]))
            ->defaultSort('work_date', 'desc')
            ->columns([
                TextColumn::make('work_date')
                    ->label('Tanggal Kerja')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('supportUser.name')
                    ->label('Support')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supportUser.team.name')
                    ->label('Tim')
                    ->placeholder('HO')
                    ->sortable(),
                TextColumn::make('reporting.outstanding.location.name')
                    ->label('Lokasi Bukti')
                    ->placeholder('Bukti belum dipilih')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge(),
                TextColumn::make('holiday_name')
                    ->label('Hari Libur')
                    ->placeholder('-'),
                TextColumn::make('rate')
                    ->label('Insentif')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Verifikasi')
                    ->badge(),
                TextColumn::make('decision_note')
                    ->label('Catatan verifikasi')
                    ->limit(70)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi oleh')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('work_date')
                    ->label('Tanggal Kerja')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->default([
                        'from' => now()->startOfMonth()->toDateString(),
                        'until' => now()->endOfMonth()->toDateString(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('work_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('work_date', '<=', $date));
                    }),
                SelectFilter::make('support_user_id')
                    ->label('Support')
                    ->options(fn (): array => User::query()
                        ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', SupportIncentive::ELIGIBLE_SUPPORT_ROLES))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('team_id')
                    ->label('Tim')
                    ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $teamId): Builder => $q->whereHas('supportUser', fn (Builder $userQuery) => $userQuery->where('team_id', $teamId)),
                    )),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(SupportIncentiveCategory::class),
                SelectFilter::make('status')
                    ->label('Verifikasi')
                    ->options(SupportIncentiveStatus::class)
                    ->default(SupportIncentiveStatus::Pending->value),
            ])
            ->headerActions([
                Action::make('exportApproved')
                    ->label('Ekspor XLSX')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => Excel::download(
                        new SupportIncentiveExport($this->tableFilters),
                        'insentif-support-disetujui-'.now()->format('Ymd-His').'.xlsx',
                    )),
            ])
            ->recordActions([
                Action::make('chooseEvidence')
                    ->label(fn (SupportIncentive $record): string => $record->reporting_id ? 'Ganti bukti' : 'Pilih bukti')
                    ->icon('heroicon-o-map-pin')
                    ->visible(fn (SupportIncentive $record): bool => $record->status === SupportIncentiveStatus::Pending)
                    ->schema([
                        Select::make('reporting_id')
                            ->label('Lokasi dan laporan bukti')
                            ->options(fn (SupportIncentive $record): array => $this->candidateReportOptions($record))
                            ->searchable()
                            ->required(),
                    ])
                    ->fillForm(fn (SupportIncentive $record): array => ['reporting_id' => $record->reporting_id])
                    ->action(function (SupportIncentive $record, array $data): void {
                        abort_unless($this->isCandidateReporting($record, (string) $data['reporting_id']), 422);

                        $record->update(['reporting_id' => $data['reporting_id']]);
                    }),
                Action::make('openEvidence')
                    ->label('Form')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(fn (SupportIncentive $record): string => 'Form Support - '.($record->reporting?->outstanding?->location?->name ?? 'Lokasi'))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SupportIncentive $record) => view(
                        'filament.pages.reports.support-incentive-form-preview',
                        ['mediaItems' => $record->reporting?->getMedia('form_support') ?? collect()],
                    ))
                    ->visible(fn (SupportIncentive $record): bool => $record->reporting_id !== null),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui insentif?')
                    ->modalDescription('Satu hari insentif akan disahkan untuk support ini.')
                    ->visible(fn (SupportIncentive $record): bool => $record->status === SupportIncentiveStatus::Pending && $record->reporting_id !== null)
                    ->action(function (SupportIncentive $record): void {
                        abort_unless(
                            $record->reporting_id && $this->isCandidateReporting($record, $record->reporting_id),
                            422,
                        );

                        $record->update([
                            'status' => SupportIncentiveStatus::Approved,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'decision_note' => null,
                        ]);
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SupportIncentive $record): bool => $record->status === SupportIncentiveStatus::Pending)
                    ->schema([
                        Textarea::make('decision_note')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(fn (SupportIncentive $record, array $data) => $record->update([
                        'status' => SupportIncentiveStatus::Rejected,
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                        'decision_note' => $data['decision_note'],
                    ])),
            ])
            ->toolbarActions([]);
    }

    /** @return array<string, string> */
    private function candidateReportOptions(SupportIncentive $record): array
    {
        return Reporting::query()
            ->with('outstanding.location')
            ->whereDate('date_visit', $record->work_date->toDateString())
            ->where('work', 'visit')
            ->whereNotNull('status')
            ->whereHas('users', fn (Builder $query) => $query->where('users.id', $record->support_user_id))
            ->whereHas('media', fn (Builder $query) => $query->where('collection_name', 'form_support'))
            ->get()
            ->mapWithKeys(fn (Reporting $reporting): array => [
                $reporting->id => ($reporting->outstanding?->location?->name ?? 'Lokasi tidak diketahui').' — '.($reporting->outstanding?->title ?? 'Reporting'),
            ])
            ->all();
    }

    private function isCandidateReporting(SupportIncentive $record, string $reportingId): bool
    {
        return Reporting::query()
            ->whereKey($reportingId)
            ->whereDate('date_visit', $record->work_date->toDateString())
            ->where('work', 'visit')
            ->whereNotNull('status')
            ->whereHas('users', fn (Builder $query) => $query->where('users.id', $record->support_user_id))
            ->whereHas('media', fn (Builder $query) => $query->where('collection_name', 'form_support'))
            ->exists();
    }

}

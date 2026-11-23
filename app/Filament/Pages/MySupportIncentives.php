<?php

namespace App\Filament\Pages;

use App\Models\SupportIncentive;
use App\Services\SupportIncentiveClaimService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MySupportIncentives extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.pages.reports.support-incentive-report';

    protected static ?string $title = 'Insentif Saya';

    protected static ?string $navigationLabel = 'Insentif Saya';

    protected static string|\UnitEnum|null $navigationGroup = 'Work';

    protected static ?int $navigationSort = 16;

    public function getSubheading(): ?string
    {
        return 'Pilih reporting Visit yang menjadi bukti pengajuan. Reporting harus sudah dikirim dan memiliki Form Support.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SupportIncentive::query()
                ->where('support_user_id', auth()->id())
                ->with(['reporting.outstanding.location', 'verifier']))
            ->defaultSort('work_date', 'desc')
            ->columns([
                TextColumn::make('work_date')
                    ->label('Tanggal Kerja')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('reporting.outstanding.location.name')
                    ->label('Lokasi')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('reporting.outstanding.title')
                    ->label('Outstanding')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge(),
                TextColumn::make('rate')
                    ->label('Insentif')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('decision_note')
                    ->label('Catatan Helpdesk')
                    ->placeholder('-')
                    ->limit(70)
                    ->toggleable(),
                TextColumn::make('verifier.name')
                    ->label('Diperiksa oleh')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('submitIncentive')
                    ->label('Ajukan Insentif')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->modalHeading('Ajukan Insentif')
                    ->modalSubmitActionLabel('Kirim Pengajuan')
                    ->schema([
                        Select::make('reporting_id')
                            ->label('Pilih lokasi / outstanding')
                            ->options(fn (): array => app(SupportIncentiveClaimService::class)
                                ->availableReportOptions(auth()->user()))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->helperText('Hanya reporting Visit pada akhir pekan atau tanggal libur yang terdaftar, sudah dilaporkan, dan memiliki Form Support.'),
                    ])
                    ->action(function (array $data): void {
                        app(SupportIncentiveClaimService::class)->submit(
                            auth()->user(),
                            (string) $data['reporting_id'],
                        );
                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title('Pengajuan insentif terkirim')
                            ->body('Helpdesk dapat memeriksa Form Support dan menyetujui pengajuan ini.')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('viewFormSupport')
                    ->label('Lihat Form Support')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (SupportIncentive $record): bool => $record->reporting_id !== null)
                    ->modalHeading(fn (SupportIncentive $record): string => 'Form Support - '.($record->reporting?->outstanding?->location?->name ?? 'Lokasi'))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SupportIncentive $record) => view(
                        'filament.pages.reports.support-incentive-form-preview',
                        ['mediaItems' => $record->reporting?->getMedia('form_support') ?? collect()],
                    )),
            ])
            ->toolbarActions([]);
    }
}

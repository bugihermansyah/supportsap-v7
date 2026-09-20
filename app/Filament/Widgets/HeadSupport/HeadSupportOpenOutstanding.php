<?php

namespace App\Filament\Widgets\HeadSupport;

use App\Enums\OutstandingStatus;
use App\Enums\ReportStatus;
use App\Models\Reporting;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class HeadSupportOpenOutstanding extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                return Reporting::query()
                    // Laporan terakhir per outstanding. MAX(id), bukan MAX(created_at):
                    // ULID sudah urut waktu, dan created_at yang kembar persis bikin
                    // outstanding muncul dua kali (58 kasus di data saat ini).
                    // Bentuk korelasi dipakai di sini karena filter di bawah menyempitkan
                    // kandidat lebih dulu; lihat HeadSupportOverview untuk bentuk lain.
                    ->whereRaw('reportings.id = (SELECT MAX(r2.id) FROM reportings r2 WHERE r2.outstanding_id = reportings.outstanding_id)')
                    ->whereHas('outstanding', function ($query) use ($user) {
                        // Rentang umur outstanding diatur lewat filter "Lama Open".
                        $query->where('status', OutstandingStatus::Open)
                            ->where('outstandings.is_implement', 0)
                            ->whereIn('reporter', ['client', 'support']);

                        if ($user && $user->team_id && ! $user->hasRole('admin')) {
                            $query->whereHas('location', function ($q) use ($user) {
                                $q->where('team_id', $user->team_id);
                            });
                        }
                    });
            })
            ->columns([
                TextColumn::make('outstanding.location.team.name')
                    ->label('Team')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->hasAnyRole(['manager', 'helpdesk']))
                    ->searchable(),
                TextColumn::make('outstanding.location.name')
                    ->label('Location')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('outstanding.title')
                    ->label('Outstanding')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('outstanding.date_in')
                    ->label('Report client')
                    ->sortable()
                    ->date('d M Y'),
                TextColumn::make('since')
                    ->label('Since (Days)')
                    // Umur tiket dalam hari kalender: dihitung dari awal hari, bukan dari
                    // jam sekarang, supaya lapor kemarin = 1 hari (bukan 2 karena pembulatan).
                    ->state(function ($record) {
                        $dateIn = $record->outstanding?->date_in;

                        if (! $dateIn) {
                            return '-';
                        }

                        return (int) Carbon::parse($dateIn)->startOfDay()->diffInDays(now()->startOfDay());
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('outstandings.date_in', $direction);
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        ! is_numeric($state) => 'gray',
                        $state >= 7 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match (true) {
                        ! is_numeric($state) => $state,
                        (int) $state === 0 => 'Hari ini',
                        default => $state.' hari lalu',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    // Belum ada status hasil = laporan terbaru masih berupa jadwal;
                    // tampilkan tahapnya (dijadwalkan / sedang dikerjakan / dibatalkan).
                    ->state(fn ($record) => $this->reportStatus($record)?->getLabel() ?? $record->state->getLabel())
                    ->color(fn ($record) => $this->reportStatus($record)?->getColor() ?? $record->state->getColor())
                    ->icon(fn ($record) => $this->reportStatus($record)?->getIcon() ?? $record->state->getIcon()),
                TextColumn::make('revisit')
                    ->label('Revisit')
                    ->sortable()
                    // Belum ada revisit karena baru dijadwalkan; pakai tanggal jadwalnya.
                    ->state(fn ($record) => $record->revisit ?: $record->date_visit)
                    ->description(fn ($record) => $record->revisit ? null : $this->scheduleNote($record))
                    ->date('d M Y'),
                TextColumn::make('pending_reason')
                    ->label('Reason Pending')
                    ->badge()
                    ->color('warning')
                    ->wrap()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('outstanding.note')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => filled($state) ? trim(strip_tags((string) $state)) : null)
                    ->wrap()
                    ->limit(100)
                    ->tooltip(fn ($state) => filled($state) ? trim(strip_tags((string) $state)) : null)
                    ->searchable()
                    ->placeholder('-')
                    ->action(
                        Action::make('editNote')
                            ->label('Edit Note')
                            ->modalHeading('Edit Note')
                            ->schema([
                                RichEditor::make('note')
                                    ->label('Note')
                                    ->columnSpanFull(),
                            ])
                            ->fillForm(fn (Reporting $record): array => [
                                'note' => $record->outstanding?->note,
                            ])
                            ->action(function (Reporting $record, array $data): void {
                                $outstanding = $record->outstanding;

                                if (! $outstanding) {
                                    return;
                                }

                                $outstanding->update([
                                    'note' => $data['note'] ?? null,
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Note berhasil diperbarui')
                                    ->send();
                            }),
                    ),
            ])
            ->recordUrl(fn ($record) => route('filament.admin.resources.outstandings.edit', ['record' => $record->outstanding->id]))
            ->filters([
                SelectFilter::make('open_age')
                    ->label('Lama Open')
                    ->options([
                        '1' => 'Lebih dari 1 hari',
                        '2' => 'Lebih dari 2 hari',
                        '3' => 'Lebih dari 3 hari',
                        'all' => 'Semua data',
                    ])
                    ->default('3')
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? '3';

                        if ($value === 'all') {
                            return $query;
                        }

                        // Umur outstanding lebih dari N hari sejak date_in.
                        return $query->whereHas(
                            'outstanding',
                            fn (Builder $q) => $q->whereDate('outstandings.date_in', '<', today()->subDays((int) $value)),
                        );
                    }),
                SelectFilter::make('reportings.status')
                    ->label('Status')
                    ->options([
                        0 => 'Pending SAP',
                        2 => 'Pending Client',
                        3 => 'Temporary',
                        4 => 'Monitoring',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    /** Status laporan yang sudah dikerjakan; null berarti baris ini masih berupa jadwal. */
    protected function reportStatus($record): ?ReportStatus
    {
        return $this->toStatus($record->status);
    }

    /** Normalisasi nilai status: bisa datang sebagai enum (hasil cast) atau nilai mentah. */
    protected function toStatus($value): ?ReportStatus
    {
        if ($value instanceof ReportStatus) {
            return $value;
        }

        return $value === null ? null : ReportStatus::tryFrom((string) $value);
    }

    /** Keterangan tanggal jadwal untuk laporan yang belum dikerjakan. */
    protected function scheduleNote($record): ?string
    {
        if (! $record->date_visit) {
            return null;
        }

        $days = (int) round(now()->startOfDay()->diffInDays(Carbon::parse($record->date_visit)->startOfDay(), false));

        return $days === 0 ? 'Jadwal hari ini' : 'Jadwal kunjungan';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['head_support', 'manager', 'helpdesk']);
    }
}

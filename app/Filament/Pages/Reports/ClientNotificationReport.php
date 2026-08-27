<?php

namespace App\Filament\Pages\Reports;

use App\Enums\ReportingState;
use App\Models\Reporting;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Progress pengiriman notifikasi email ke client.
 *
 * Aturan yang dipakai (disepakati bersama pemilik proses):
 *   - Wajib kirim  : setiap laporan (state = reported) pada outstanding yang
 *                    pelapornya `client`.
 *   - Tepat waktu  : email terkirim paling lambat H+1 dari tanggal kunjungan.
 *   - Tim          : diambil dari team pemilik lokasi (locations.team_id);
 *                    outstandings.team_id tidak dipakai karena kosong di
 *                    7.744 dari 9.527 baris.
 */
class ClientNotificationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected string $view = 'filament.pages.reports.report-count-outstanding';

    protected static ?string $title = 'Progress Notifikasi Client';

    protected static ?string $navigationLabel = 'Notifikasi Client';

    protected static string|\UnitEnum|null $navigationGroup = 'Support Reports';

    protected static ?int $navigationSort = 20;

    /** Batas hari email masih dianggap tepat waktu, dihitung dari tanggal kunjungan. */
    public const GRACE_DAYS = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([
            'manager', 'owner', 'admin', 'super_admin', 'helpdesk', 'head_support',
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Team::query())
            ->modifyQueryUsing(function (Builder $query, HasTable $livewire) {
                $year = $livewire->tableFilters['year']['value'] ?? now()->year;
                $month = $livewire->tableFilters['month']['value'] ?? null;

                $user = auth()->user();

                // Head support hanya melihat timnya sendiri.
                if ($user?->hasRole('head_support') && $user->team_id) {
                    $query->where('id', $user->team_id);
                }

                // Tiap pemanggilan menghasilkan builder baru, jadi antar-kolom tidak
                // saling menimpa kondisi.
                $base = static::baseReportingQuery($year, $month);

                $query
                    ->select('teams.*')
                    ->addSelect(['wajib_count' => $base()
                        ->selectRaw('COUNT(*)'),
                    ])
                    ->addSelect(['terkirim_count' => $base()
                        ->selectRaw('COUNT(*)')
                        ->whereNotNull('reportings.send_mail_at'),
                    ])
                    ->addSelect(['ontime_count' => $base()
                        ->selectRaw('COUNT(*)')
                        ->whereNotNull('reportings.send_mail_at')
                        ->whereRaw('DATE(reportings.send_mail_at) <= DATE_ADD(reportings.date_visit, INTERVAL '.static::GRACE_DAYS.' DAY)'),
                    ])
                    ->addSelect(['avg_lag' => $base()
                        ->selectRaw('ROUND(AVG(DATEDIFF(DATE(reportings.send_mail_at), reportings.date_visit)), 1)')
                        ->whereNotNull('reportings.send_mail_at'),
                    ]);
            })
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Team')
                    ->sortable(),
                TextColumn::make('wajib_count')
                    ->label('Wajib kirim')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('terkirim_count')
                    ->label('Terkirim')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('ontime_count')
                    ->label('Tepat waktu')
                    ->tooltip('Terkirim paling lambat H+'.static::GRACE_DAYS.' dari tanggal kunjungan')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('late_count')
                    ->label('Telat')
                    ->alignCenter()
                    ->state(fn (Team $record) => max(0, (int) $record->terkirim_count - (int) $record->ontime_count))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('pending_count')
                    ->label('Belum dikirim')
                    ->alignCenter()
                    ->state(fn (Team $record) => max(0, (int) $record->wajib_count - (int) $record->terkirim_count))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('compliance')
                    ->label('% Patuh')
                    ->alignCenter()
                    ->badge()
                    ->state(function (Team $record) {
                        $wajib = (int) $record->wajib_count;

                        return $wajib > 0
                            ? (int) round((int) $record->terkirim_count / $wajib * 100)
                            : null;
                    })
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : $state.'%')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('avg_lag')
                    ->label('Rata-rata jeda')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : $state.' hari')
                    ->tooltip('Selisih hari antara tanggal kunjungan dan tanggal email terkirim'),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(fn () => collect(range(now()->year, now()->year - 4))
                        ->mapWithKeys(fn ($y) => [$y => $y])
                        ->all())
                    ->default(now()->year)
                    ->selectablePlaceholder(false)
                    // Nilainya dibaca sendiri di modifyQueryUsing; tanpa query() kosong,
                    // Filament akan mencari kolom `teams.year` yang tidak ada.
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options(fn () => collect(range(1, 12))
                        ->mapWithKeys(fn ($m) => [$m => Carbon::create(null, $m, 1)->translatedFormat('F')])
                        ->all())
                    ->placeholder('Semua bulan')
                    ->query(fn (Builder $query) => $query),
            ])
            ->recordActions([
                Action::make('pending')
                    ->label('Lihat tunggakan')
                    ->icon('heroicon-m-inbox-stack')
                    ->color('danger')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn (Team $record) => 'Belum dikirim — '.$record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (Team $record) => (int) $record->wajib_count > (int) $record->terkirim_count)
                    ->modalContent(fn (Team $record) => view(
                        'filament.pages.reports.partials.client-notification-pending',
                        ['rows' => $this->pendingRows($record)],
                    )),
            ])
            ->toolbarActions([]);
    }

    /** Laporan pada tiket client yang belum dikirim emailnya, untuk satu team. */
    public function pendingRows(Team $team): Collection
    {
        $year = $this->tableFilters['year']['value'] ?? now()->year;
        $month = $this->tableFilters['month']['value'] ?? null;

        return Reporting::query()
            ->reported()
            ->whereNull('send_mail_at')
            ->whereHas('outstanding', function (Builder $q) use ($team) {
                $q->where('reporter', 'client')
                    ->whereHas('location', fn (Builder $l) => $l->where('team_id', $team->id));
            })
            ->when($year, fn (Builder $q) => $q->whereYear('date_visit', $year))
            ->when($month, fn (Builder $q) => $q->whereMonth('date_visit', $month))
            ->with(['outstanding.location', 'users'])
            ->orderBy('date_visit')
            ->limit(100)
            ->get();
    }

    /**
     * Subquery dasar: laporan wajib-notif milik team pada baris yang sedang dirender.
     * Dibungkus closure supaya tiap kolom memakai salinan sendiri.
     */
    protected static function baseReportingQuery(?int $year, ?int $month): callable
    {
        return fn () => Reporting::query()
            ->join('outstandings', 'outstandings.id', '=', 'reportings.outstanding_id')
            ->join('locations', 'locations.id', '=', 'outstandings.location_id')
            ->whereColumn('locations.team_id', 'teams.id')
            ->where('outstandings.reporter', 'client')
            ->where('reportings.state', ReportingState::Reported)
            ->when($year, fn (Builder $q) => $q->whereYear('reportings.date_visit', $year))
            ->when($month, fn (Builder $q) => $q->whereMonth('reportings.date_visit', $month));
    }
}

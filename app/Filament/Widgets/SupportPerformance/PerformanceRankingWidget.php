<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Enums\BorrowRequestStatus;
use App\Enums\OutstandingStatus;
use App\Enums\ReportingState;
use App\Filament\Pages\UserKpiReport;
use App\Models\Outstanding;
use App\Models\QuizAttempt;
use App\Models\Reporting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PerformanceRankingWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $teamId = $this->filters['team_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }

        // Filter yang sama dipakai untuk rata-rata KPI dan jumlah laporan.
        $reportingFilters = function (Builder $query) use ($startDate, $endDate): void {
            $query->where('reportings.state', ReportingState::Reported);
            if ($startDate) {
                $query->whereDate('date_visit', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('date_visit', '<=', $endDate);
            }
        };

        return $table
            ->query(
                User::role(['support', 'head_support'])
                    // select() harus mendahului withCount/withAvg/addSelect di bawah:
                    // select() menimpa daftar kolom, addSelect() menambah.
                    ->select('users.*')
                    ->where('status', '!=', 0)
                    ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
                    // Hanya baris berstatus laporan; jadwal & pembatalan tidak dihitung.
                    ->withAvg(['reportings as kpi' => function (Builder $query) use ($reportingFilters) {
                        $reportingFilters($query);
                    }], 'score')
                    ->withCount(['reportings as reportings_count' => function (Builder $query) use ($reportingFilters) {
                        $reportingFilters($query);
                    }])
                    // Outstanding yang DIKERJAKAN engineer (lewat laporannya), bukan yang
                    // dibuat olehnya — relasi User::outstandings memakai outstandings.user_id
                    // yang artinya pembuat tiket.
                    ->addSelect(['outstandings_count' => Outstanding::query()
                        ->selectRaw('COUNT(DISTINCT outstandings.id)')
                        ->join('reportings', 'reportings.outstanding_id', '=', 'outstandings.id')
                        ->join('reporting_users', 'reporting_users.reporting_id', '=', 'reportings.id')
                        ->whereColumn('reporting_users.user_id', 'users.id')
                        ->where('outstandings.status', OutstandingStatus::Open)
                        ->when($startDate, fn ($q) => $q->whereDate('outstandings.created_at', '>=', $startDate))
                        ->when($endDate, fn ($q) => $q->whereDate('outstandings.created_at', '<=', $endDate)),
                    ])
                    // Rata-rata skor quiz (persen jawaban benar) dari sesi yang sudah selesai.
                    ->addSelect(['quiz_score' => QuizAttempt::query()
                        ->selectRaw('ROUND(AVG(correct_answers / total_questions * 100))')
                        ->whereColumn('quiz_attempts.user_id', 'users.id')
                        ->whereNotNull('finished_at')
                        ->where('total_questions', '>', 0)
                        ->when($startDate, fn ($q) => $q->whereDate('finished_at', '>=', $startDate))
                        ->when($endDate, fn ($q) => $q->whereDate('finished_at', '<=', $endDate)),
                    ])
                    // Ketepatan lapor: laporan yang selesai ditulis paling lambat pada
                    // hari kunjungan itu sendiri (dasar penalti keterlambatan di rumus KPI).
                    ->addSelect(['ontime_rate' => Reporting::query()
                        ->selectRaw('ROUND(AVG(CASE WHEN DATE(COALESCE(reportings.end_work, reportings.updated_at)) <= reportings.date_visit THEN 100 ELSE 0 END))')
                        ->join('reporting_users', 'reporting_users.reporting_id', '=', 'reportings.id')
                        ->whereColumn('reporting_users.user_id', 'users.id')
                        ->where('reportings.state', ReportingState::Reported)
                        ->when($startDate, fn ($q) => $q->whereDate('reportings.date_visit', '>=', $startDate))
                        ->when($endDate, fn ($q) => $q->whereDate('reportings.date_visit', '<=', $endDate)),
                    ])
                    // Kelengkapan: laporan yang punya foto DAN form support (dua penalti
                    // terbesar di rumus KPI).
                    ->addSelect(['complete_rate' => Reporting::query()
                        ->selectRaw("ROUND(AVG(CASE WHEN EXISTS (SELECT 1 FROM media m1 WHERE m1.model_type = 'App\\\\Models\\\\Reporting' AND m1.model_id = reportings.id AND m1.collection_name = 'attachments')
                                                AND EXISTS (SELECT 1 FROM media m2 WHERE m2.model_type = 'App\\\\Models\\\\Reporting' AND m2.model_id = reportings.id AND m2.collection_name = 'form_support')
                                              THEN 100 ELSE 0 END))")
                        ->join('reporting_users', 'reporting_users.reporting_id', '=', 'reportings.id')
                        ->whereColumn('reporting_users.user_id', 'users.id')
                        ->where('reportings.state', ReportingState::Reported)
                        ->when($startDate, fn ($q) => $q->whereDate('reportings.date_visit', '>=', $startDate))
                        ->when($endDate, fn ($q) => $q->whereDate('reportings.date_visit', '<=', $endDate)),
                    ])
                    ->addSelect(['quiz_count' => QuizAttempt::query()
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('quiz_attempts.user_id', 'users.id')
                        ->whereNotNull('finished_at')
                        ->when($startDate, fn ($q) => $q->whereDate('finished_at', '>=', $startDate))
                        ->when($endDate, fn ($q) => $q->whereDate('finished_at', '<=', $endDate)),
                    ])
                    ->withCount(['borrowRequests as borrows_count' => function (Builder $query) use ($startDate, $endDate) {
                        $query->whereNotIn('status', [BorrowRequestStatus::Returned, BorrowRequestStatus::Cancelled]);
                        // Ikut rentang tanggal halaman, sama seperti stat Borrow Request.
                        if ($startDate) {
                            $query->whereDate('created_at', '>=', $startDate);
                        }
                        if ($endDate) {
                            $query->whereDate('created_at', '<=', $endDate);
                        }
                    }])
                    ->withSum(['reportingUsers as total_distance' => function (Builder $query) use ($startDate, $endDate) {
                        if ($startDate || $endDate) {
                            $query->whereHas('reporting', function ($q) use ($startDate, $endDate) {
                                if ($startDate) {
                                    $q->whereDate('date_visit', '>=', $startDate);
                                }
                                if ($endDate) {
                                    $q->whereDate('date_visit', '<=', $endDate);
                                }
                            });
                        }
                    }], 'distance')
            )
            ->defaultSort('kpi', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('Rank')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Support')
                    ->description(fn (User $record) => $record->roles->first()?->name === 'head_support' ? 'Head Support' : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kpi')
                    ->label('KPI')
                    ->numeric(1)
                    ->sortable()
                    ->badge()
                    ->placeholder('-')
                    // Ambangnya mengikuti grade di General Settings, bukan angka mati,
                    // supaya konsisten dengan penilaian di halaman laporan.
                    ->color(fn ($state) => $state === null ? 'gray' : Reporting::getScoreColor((int) round((float) $state)))
                    ->tooltip(fn ($state) => $state === null
                        ? 'Belum ada laporan yang dinilai'
                        : 'Grade '.Reporting::getScoreGrade((int) round((float) $state))),
                Tables\Columns\TextColumn::make('reportings_count')
                    ->label('Reporting')
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstandings_count')
                    ->label('Outstanding')
                    ->sortable(),
                Tables\Columns\TextColumn::make('borrows_count')
                    ->label('Borrow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_distance')
                    ->label('Distance (KM)')
                    ->numeric(2)
                    ->sortable(),
                // Kolomnya baru muncul kalau memang sudah ada yang mengerjakan quiz.
                Tables\Columns\TextColumn::make('quiz_score')
                    ->label('Quiz')
                    ->badge()
                    ->sortable()
                    ->placeholder('-')
                    ->visible(fn () => QuizAttempt::query()->whereNotNull('finished_at')->exists())
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (User $record) => $record->quiz_count > 0
                        ? $record->quiz_count.'x mengerjakan quiz'
                        : 'Belum pernah ikut quiz'),
                // Menggantikan kolom "SLA" yang dulunya selalu 100%.
                Tables\Columns\TextColumn::make('ontime_rate')
                    ->label('Tepat Waktu')
                    ->badge()
                    ->sortable()
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : $state.'%')
                    ->tooltip('Laporan yang selesai ditulis paling lambat di hari kunjungan')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 75 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('complete_rate')
                    ->label('Lengkap')
                    ->badge()
                    ->sortable()
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : $state.'%')
                    ->tooltip('Laporan yang menyertakan foto dan form support')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 75 => 'warning',
                        default => 'danger',
                    }),
                // Kolom "Status" (Online/Offline) dihapus: tidak ada sumber datanya,
                // nilainya selalu 'Online'.
            ])
            ->actions([
                Action::make('view_profile')
                    ->label('KPI Raport')
                    ->icon('heroicon-m-user')
                    ->url(fn (User $record): string => UserKpiReport::getUrl(['user_id' => $record->id])),
            ]);
    }
}

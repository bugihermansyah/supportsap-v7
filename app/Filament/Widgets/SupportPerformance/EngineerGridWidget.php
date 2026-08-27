<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Enums\BorrowRequestStatus;
use App\Enums\OutstandingStatus;
use App\Enums\ReportingState;
use App\Models\Outstanding;
use App\Models\QuizAttempt;
use App\Models\ReportingUser;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EngineerGridWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.support-performance.engineer-grid-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }

    /**
     * Semua angka diambil lewat subquery dalam SATU query (pola yang sama dengan
     * PerformanceRankingWidget). Sebelumnya tiap orang menembak 7 query sendiri —
     * 19 support = 138 query, diulang tiap polling 30 detik.
     */
    public function getEngineersProperty(): Collection
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $teamId = $this->filters['team_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }

        // Hanya baris yang benar-benar laporan; jadwal & pembatalan tidak dihitung.
        $reportingFilters = function (Builder $query) use ($startDate, $endDate): void {
            $query->where('reportings.state', ReportingState::Reported);
            if ($startDate) {
                $query->whereDate('date_visit', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('date_visit', '<=', $endDate);
            }
        };

        $engineers = User::role(['support', 'head_support'])
            // select() harus mendahului withCount/withAvg/addSelect: select() menimpa
            // daftar kolom, addSelect() menambah.
            ->select('users.*')
            ->with('roles')
            ->where('status', '!=', 0)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->withCount(['reportings as reporting_count' => $reportingFilters])
            ->withAvg(['reportings as kpi' => $reportingFilters], 'score')
            ->withMax(['reportings as last_reporting_date' => $reportingFilters], 'date_visit')
            // Outstanding yang DIKERJAKAN (lewat laporannya), bukan yang dibuat olehnya.
            ->addSelect(['outstanding_count' => Outstanding::query()
                ->selectRaw('COUNT(DISTINCT outstandings.id)')
                ->join('reportings', 'reportings.outstanding_id', '=', 'outstandings.id')
                ->join('reporting_users', 'reporting_users.reporting_id', '=', 'reportings.id')
                ->whereColumn('reporting_users.user_id', 'users.id')
                ->where('outstandings.status', OutstandingStatus::Open)
                ->when($startDate, fn ($q) => $q->whereDate('outstandings.created_at', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('outstandings.created_at', '<=', $endDate)),
            ])
            ->withCount(['borrowRequests as borrow_count' => function (Builder $query) use ($startDate, $endDate) {
                $query->whereNotIn('status', [BorrowRequestStatus::Returned, BorrowRequestStatus::Cancelled]);
                if ($startDate) {
                    $query->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->whereDate('created_at', '<=', $endDate);
                }
            }])
            ->addSelect(['distance' => ReportingUser::query()
                ->selectRaw('COALESCE(SUM(distance), 0)')
                ->whereColumn('reporting_users.user_id', 'users.id')
                ->when($startDate || $endDate, fn ($q) => $q->whereHas('reporting', function ($r) use ($startDate, $endDate) {
                    if ($startDate) {
                        $r->whereDate('date_visit', '>=', $startDate);
                    }
                    if ($endDate) {
                        $r->whereDate('date_visit', '<=', $endDate);
                    }
                })),
            ])
            // Skor quiz: rata-rata persen jawaban benar dari sesi yang sudah selesai.
            ->addSelect(['quiz_score' => QuizAttempt::query()
                ->selectRaw('ROUND(AVG(correct_answers / total_questions * 100))')
                ->whereColumn('quiz_attempts.user_id', 'users.id')
                ->whereNotNull('finished_at')
                ->where('total_questions', '>', 0)
                ->when($startDate, fn ($q) => $q->whereDate('finished_at', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('finished_at', '<=', $endDate)),
            ])
            ->addSelect(['quiz_count' => QuizAttempt::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('quiz_attempts.user_id', 'users.id')
                ->whereNotNull('finished_at')
                ->when($startDate, fn ($q) => $q->whereDate('finished_at', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('finished_at', '<=', $endDate)),
            ])
            ->get();

        // Satu-satunya perhitungan yang tersisa; tidak menyentuh database lagi.
        foreach ($engineers as $engineer) {
            $engineer->last_reporting = $engineer->last_reporting_date
                ? Carbon::parse($engineer->last_reporting_date)->diffForHumans()
                : '-';
        }

        return $engineers;
    }
}

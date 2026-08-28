<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Enums\BorrowRequestStatus;
use App\Enums\OutstandingStatus;
use App\Models\BorrowRequest;
use App\Models\Outstanding;
use App\Models\QuizAttempt;
use App\Models\Reporting;
use App\Models\ReportingUser;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TeamSummaryWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    // Cache the widget for 30 seconds as per point 20
    protected function getPollingInterval(): ?string
    {
        return '30s';
    }

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $teamId = $this->filters['team_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }
        // admin, super_admin, manager, helpdesk → use filter value (null = all)

        // Base reporting query — hanya baris yang benar-benar laporan (bukan jadwal
        // yang belum dikerjakan atau jadwal yang dibatalkan).
        $reportingQuery = Reporting::query()->reported();
        if ($startDate) {
            $reportingQuery->whereDate('date_visit', '>=', $startDate);
        }
        if ($endDate) {
            $reportingQuery->whereDate('date_visit', '<=', $endDate);
        }
        if ($teamId) {
            $reportingQuery->whereHas('users', fn (Builder $q) => $q->where('users.team_id', $teamId));
        }

        $reportingToday = (clone $reportingQuery)->whereDate('date_visit', today())->count();

        // Base outstanding query
        $outstandingQuery = Outstanding::query();
        if ($teamId) {
            $outstandingQuery->where('team_id', $teamId);
        }
        if ($startDate) {
            $outstandingQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $outstandingQuery->whereDate('created_at', '<=', $endDate);
        }

        // Count open outstandings
        $totalOutstanding = (clone $outstandingQuery)->where('status', OutstandingStatus::Open)->count();

        // Borrow requests — ikut filter yang sama dengan stat lain di baris ini.
        $borrowQuery = BorrowRequest::query();
        if ($teamId) {
            $borrowQuery->whereHas('requester', fn (Builder $q) => $q->where('team_id', $teamId));
        }
        if ($startDate) {
            $borrowQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $borrowQuery->whereDate('created_at', '<=', $endDate);
        }
        $totalBorrow = (clone $borrowQuery)->whereNotIn('status', [
            BorrowRequestStatus::Returned,
            BorrowRequestStatus::Cancelled,
        ])->count();

        // Average KPI (Average score from reportings)
        $avgKpi = (clone $reportingQuery)->avg('score') ?? 0;

        // Total distance
        $reportingUserQuery = ReportingUser::query();
        if ($teamId) {
            $reportingUserQuery->whereHas('user', fn ($q) => $q->where('team_id', $teamId));
        }
        if ($startDate || $endDate) {
            $reportingUserQuery->whereHas('reporting', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->whereDate('date_visit', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('date_visit', '<=', $endDate);
                }
            });
        }
        $totalDistance = (clone $reportingUserQuery)->sum('distance') ?? 0;

        // Rata-rata skor quiz tim (persen jawaban benar), hanya sesi yang sudah selesai.
        $quizQuery = QuizAttempt::query()
            ->fromClosedSessions()
            ->whereNotNull('finished_at')
            ->where('total_questions', '>', 0);
        if ($teamId) {
            $quizQuery->whereHas('user', fn (Builder $q) => $q->where('users.team_id', $teamId));
        }
        if ($startDate) {
            $quizQuery->whereDate('finished_at', '>=', $startDate);
        }
        if ($endDate) {
            $quizQuery->whereDate('finished_at', '<=', $endDate);
        }

        $quizCount = (clone $quizQuery)->count();
        $avgQuiz = $quizCount > 0
            ? (int) round((clone $quizQuery)->avg(DB::raw('correct_answers / total_questions * 100')))
            : null;

        // Kunjungan ke lokasi luar kota. Penandanya locations.area_status = 'out'
        // (dipakai juga oleh job perhitungan jarak), bukan "bukan HO".
        $outsideCityCount = (clone $reportingQuery)
            ->whereHas('outstanding.location', fn ($q) => $q->where('area_status', 'out'))
            ->count();

        $stats = [
            Stat::make('Reporting Today', $reportingToday)
                ->icon('heroicon-o-document-text'),
            Stat::make('Outstanding', $totalOutstanding)
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Borrow Request', $totalBorrow)
                ->icon('heroicon-o-cube'),
            Stat::make('Average KPI', number_format($avgKpi, 1))
                ->description($avgKpi > 0 ? 'Grade '.Reporting::getScoreGrade((int) round($avgKpi)) : null)
                ->color($avgKpi > 0 ? Reporting::getScoreColor((int) round($avgKpi)) : 'gray')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Total Distance', number_format($totalDistance, 2).' KM')
                ->icon('heroicon-o-map'),
            Stat::make('Outside City', $outsideCityCount)
                ->icon('heroicon-o-globe-alt'),
        ];

        // Hanya tampil kalau memang sudah ada quiz yang dikerjakan pada rentang ini.
        if ($avgQuiz !== null) {
            $stats[] = Stat::make('Quiz Score', $avgQuiz)
                ->description($quizCount.'x dikerjakan')
                ->color($avgQuiz >= 70 ? 'success' : ($avgQuiz >= 50 ? 'warning' : 'danger'))
                ->icon('heroicon-o-academic-cap');
        }

        return $stats;
    }
}

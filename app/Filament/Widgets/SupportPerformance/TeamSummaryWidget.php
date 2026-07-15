<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Enums\BorrowRequestStatus;
use App\Models\BorrowRequest;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\ReportingUser;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class TeamSummaryWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

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
        $userId = $this->filters['user_id'] ?? null;
        $customerId = $this->filters['customer_id'] ?? null;
        $locationId = $this->filters['location_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }
        // admin, super_admin, manager, helpdesk → use filter value (null = all)

        // Base user query for engineers
        $userQuery = User::role('support')->where('status', '!=', 0);
        if ($teamId) {
            $userQuery->where('team_id', $teamId);
        }
        if ($userId) {
            $userQuery->where('id', $userId);
        }

        $totalEngineer = (clone $userQuery)->count();
        // Online and Offline mockup
        $online = 0; 
        $offline = max(0, $totalEngineer - $online);

        // Base reporting query
        $reportingQuery = Reporting::query();
        if ($startDate) $reportingQuery->whereDate('date_visit', '>=', $startDate);
        if ($endDate) $reportingQuery->whereDate('date_visit', '<=', $endDate);
        if ($teamId || $userId) {
            $reportingQuery->whereHas('users', function(Builder $q) use ($teamId, $userId) {
                if ($userId) $q->where('users.id', $userId);
                if ($teamId) $q->where('users.team_id', $teamId);
            });
        }
        if ($locationId) {
            $reportingQuery->whereHas('outstanding', fn($q) => $q->where('location_id', $locationId));
        }
        if ($customerId) {
            $reportingQuery->whereHas('outstanding.location', fn($q) => $q->where('customer_id', $customerId));
        }
        
        $reportingToday = (clone $reportingQuery)->whereDate('date_visit', today())->count();

        // Base outstanding query
        $outstandingQuery = Outstanding::query();
        if ($teamId) $outstandingQuery->where('team_id', $teamId);
        if ($userId) $outstandingQuery->where('user_id', $userId);
        if ($locationId) $outstandingQuery->where('location_id', $locationId);
        if ($customerId) $outstandingQuery->whereHas('location', fn($q) => $q->where('customer_id', $customerId));
        if ($startDate) $outstandingQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $outstandingQuery->whereDate('created_at', '<=', $endDate);
        
        // Count open outstandings
        $totalOutstanding = (clone $outstandingQuery)->where('status', \App\Enums\OutstandingStatus::Open)->count();

        // Borrow requests
        $borrowQuery = BorrowRequest::query();
        if ($userId) $borrowQuery->where('requester_id', $userId);
        $totalBorrow = (clone $borrowQuery)->whereNotIn('status', [
            BorrowRequestStatus::Returned, 
            BorrowRequestStatus::Cancelled
        ])->count();

        // Average KPI (Average score from reportings)
        $avgKpi = (clone $reportingQuery)->avg('score') ?? 0;

        // Total distance
        $reportingUserQuery = ReportingUser::query();
        if ($userId) $reportingUserQuery->where('user_id', $userId);
        if ($teamId) $reportingUserQuery->whereHas('user', fn($q) => $q->where('team_id', $teamId));
        if ($startDate || $endDate) {
            $reportingUserQuery->whereHas('reporting', function($q) use ($startDate, $endDate) {
                if ($startDate) $q->whereDate('date_visit', '>=', $startDate);
                if ($endDate) $q->whereDate('date_visit', '<=', $endDate);
            });
        }
        $totalDistance = (clone $reportingUserQuery)->sum('distance') ?? 0;

        // Outside city (Mocked for now or count based on location is_outside_city)
        $outsideCityCount = (clone $reportingQuery)
            ->whereHas('outstanding.location', fn($q) => $q->where('is_ho', false)) // Assuming non-HO is outside city as a fallback for now
            ->count();

        return [
            // Stat::make('Total Support', $totalEngineer)
            //     ->icon('heroicon-o-users'),
            // Stat::make('Online', $online)
            //     ->color('success')
            //     ->icon('heroicon-o-signal'),
            // Stat::make('Offline', $offline)
            //     ->color('gray')
            //     ->icon('heroicon-o-signal-slash'),
            Stat::make('Reporting Today', $reportingToday)
                ->icon('heroicon-o-document-text'),
            Stat::make('Outstanding', $totalOutstanding)
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Borrow Request', $totalBorrow)
                ->icon('heroicon-o-cube'),
            Stat::make('Average KPI', number_format($avgKpi, 1))
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Total Distance', number_format($totalDistance, 2) . ' KM')
                ->icon('heroicon-o-map'),
            Stat::make('Outside City', $outsideCityCount)
                ->icon('heroicon-o-globe-alt'),
            // Stat::make('SLA Achievement', '100%') // Mock for now
            //     ->color('success')
            //     ->icon('heroicon-o-check-badge'),
        ];
    }
}

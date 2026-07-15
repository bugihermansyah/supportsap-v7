<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Enums\BorrowRequestStatus;
use App\Models\BorrowRequest;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\ReportingUser;
use App\Models\User;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class EngineerGridWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;
    protected string $view = 'filament.widgets.support-performance.engineer-grid-widget';
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }

    public function getEngineersProperty()
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

        $query = User::role('support')->where('status', '!=', 0);
        
        if ($teamId) {
            $query->where('team_id', $teamId);
        }
        if ($userId) {
            $query->where('id', $userId);
        }

        $engineers = $query->get();

        foreach ($engineers as $engineer) {
            // Reporting count
            $reportingQuery = Reporting::whereHas('users', fn($q) => $q->where('users.id', $engineer->id));
            if ($startDate) $reportingQuery->whereDate('date_visit', '>=', $startDate);
            if ($endDate) $reportingQuery->whereDate('date_visit', '<=', $endDate);
            if ($locationId) $reportingQuery->whereHas('outstanding', fn($q) => $q->where('location_id', $locationId));
            if ($customerId) $reportingQuery->whereHas('outstanding.location', fn($q) => $q->where('customer_id', $customerId));
            
            $engineer->reporting_count = (clone $reportingQuery)->count();
            
            // Average KPI (from reportings score)
            $engineer->kpi = (clone $reportingQuery)->avg('score') ?? 0;
            
            // Last reporting
            $lastReporting = (clone $reportingQuery)->latest('date_visit')->first();
            $engineer->last_reporting = $lastReporting && $lastReporting->date_visit ? $lastReporting->date_visit->diffForHumans() : '-';

            // Outstanding count
            $outstandingQuery = Outstanding::where('user_id', $engineer->id)
                ->where('status', \App\Enums\OutstandingStatus::Open);
            if ($locationId) $outstandingQuery->where('location_id', $locationId);
            if ($customerId) $outstandingQuery->whereHas('location', fn($q) => $q->where('customer_id', $customerId));
            if ($startDate) $outstandingQuery->whereDate('created_at', '>=', $startDate);
            if ($endDate) $outstandingQuery->whereDate('created_at', '<=', $endDate);
            
            $engineer->outstanding_count = $outstandingQuery->count();

            // Borrow request count
            $borrowQuery = BorrowRequest::where('requester_id', $engineer->id)
                ->whereNotIn('status', [BorrowRequestStatus::Returned, BorrowRequestStatus::Cancelled]);
            $engineer->borrow_count = $borrowQuery->count();

            // Distance
            $reportingUserQuery = ReportingUser::where('user_id', $engineer->id);
            if ($startDate || $endDate) {
                $reportingUserQuery->whereHas('reporting', function($q) use ($startDate, $endDate) {
                    if ($startDate) $q->whereDate('date_visit', '>=', $startDate);
                    if ($endDate) $q->whereDate('date_visit', '<=', $endDate);
                });
            }
            $engineer->distance = $reportingUserQuery->sum('distance') ?? 0;

            // Mock status
            $engineer->status_label = 'Online';
            $engineer->status_color = 'success'; // success, danger, warning, gray
        }

        return $engineers;
    }
}

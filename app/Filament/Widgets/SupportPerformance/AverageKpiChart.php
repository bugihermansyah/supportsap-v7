<?php

namespace App\Filament\Widgets\SupportPerformance;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Reporting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AverageKpiChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Average KPI Trend';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth()->toDateString();
        $teamId = $this->filters['team_id'] ?? null;
        $userId = $this->filters['user_id'] ?? null;
        $customerId = $this->filters['customer_id'] ?? null;
        $locationId = $this->filters['location_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $labels = [];
        $data = [];

        // Build base query
        $query = Reporting::query();
        if ($teamId || $userId) {
            $query->whereHas('users', function(Builder $q) use ($teamId, $userId) {
                if ($userId) $q->where('users.id', $userId);
                if ($teamId) $q->where('users.team_id', $teamId);
            });
        }
        if ($locationId) {
            $query->whereHas('outstanding', fn($q) => $q->where('location_id', $locationId));
        }
        if ($customerId) {
            $query->whereHas('outstanding.location', fn($q) => $q->where('customer_id', $customerId));
        }

        // Generate data points
        $period = new \DatePeriod(
            $start,
            new \DateInterval('P1D'),
            $end->copy()->addDay()
        );

        foreach ($period as $date) {
            $labels[] = $date->format('M d');
            $avgScore = (clone $query)->whereDate('date_visit', $date->format('Y-m-d'))->avg('score') ?? 0;
            $data[] = round($avgScore, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Average KPI',
                    'data' => $data,
                    'borderColor' => '#10b981', // Tailwind success color roughly
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

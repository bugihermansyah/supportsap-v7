<?php

namespace App\Filament\Widgets\Reports;

use App\Models\BorrowRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class BorrowRequestChartWidget extends ChartWidget
{
    // protected static ?string $heading = 'Borrow Requests per Month (Current Year)';
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    protected function getData(): array
    {
        // Get the counts per month for the current year
        $monthlyCounts = BorrowRequest::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = $monthlyCounts[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Borrow Requests',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

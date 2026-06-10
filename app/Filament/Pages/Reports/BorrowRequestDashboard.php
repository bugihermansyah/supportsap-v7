<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Reports\BorrowRequestChartWidget;
use App\Filament\Widgets\Reports\BorrowRequestStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class BorrowRequestDashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Request Reports';
    protected static ?string $title = 'Borrow Request Dashboard';
    protected static ?int $navigationSort = 1;
    protected static string $routePath = 'borrow-request-dashboard';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function getWidgets(): array
    {
        return [
            BorrowRequestStatsWidget::class,
            BorrowRequestChartWidget::class,
        ];
    }
}

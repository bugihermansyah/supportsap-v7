<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Report\MonthlyTeamReportChart;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class MonthlyTeamReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Monthly Team Report';

    protected static ?string $navigationLabel = 'Monthly Team Report';

    protected static string|\UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 10;

    protected function getHeaderWidgets(): array
    {
        return [
            MonthlyTeamReportChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'head_support']);
    }
}

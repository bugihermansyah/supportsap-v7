<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Widgets;

use App\Models\Outstanding;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OutstandingStat extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Open', Outstanding::where('status', 0)->where('reporter', 'preventif')->count()),
            Stat::make('Progress', Outstanding::where('status', 0)->where('reporter', 'preventif')->has('reportings')->count()),
            Stat::make('Total Closed', Outstanding::where('status', 1)->where('reporter', 'preventif')->count()),
        ];
    }
}

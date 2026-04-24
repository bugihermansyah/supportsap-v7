<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\HeadPreventive\HeadPreventiveOverview;
use App\Filament\Widgets\HeadSupport\HeadSupportOverview;
use App\Filament\Widgets\Preventif\PreventifSchedules;
use App\Filament\Widgets\Support\Schedules;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            // Head Support widgets
            HeadSupportOverview::class,

            // Head Preventive widgets
            HeadPreventiveOverview::class,

            // Support widgets
            Schedules::class,

            // Preventif widgets
            PreventifSchedules::class,
        ];
    }
}
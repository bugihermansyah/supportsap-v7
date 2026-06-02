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
        $widgets = [
            // Head Support widgets
            HeadSupportOverview::class,

            // Head Preventive widgets
            HeadPreventiveOverview::class,

            // Preventif widgets
            PreventifSchedules::class,
        ];

        // Support widgets — hanya tampilkan Schedules jika bukan head_support
        // head_support melihat Schedules di ScheduleDashboard
        if (!auth()->user()?->hasRole('head_support')) {
            $widgets[] = Schedules::class;
        }

        return $widgets;
    }
}
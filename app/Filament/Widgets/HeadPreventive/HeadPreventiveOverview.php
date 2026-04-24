<?php

namespace App\Filament\Widgets\HeadPreventive;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HeadPreventiveOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Jadwal Preventif', '18')
                ->description('Minggu ini')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->chart([4, 6, 3, 5, 7, 4, 6]),
            Stat::make('Selesai', '42')
                ->description('Bulan ini')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([8, 10, 7, 12, 9, 11, 8]),
            Stat::make('Pending', '5')
                ->description('Belum dikerjakan')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart([2, 1, 3, 2, 4, 1, 3]),
            Stat::make('Tim Preventif', '6')
                ->description('Aktif bekerja')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('head_preventive');
    }
}

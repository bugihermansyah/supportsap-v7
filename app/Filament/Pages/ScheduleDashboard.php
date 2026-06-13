<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Support\Schedules;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class ScheduleDashboard extends BaseDashboard
{
    protected static ?string $title = 'Schedules';

    protected static string $routePath = 'schedule-dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string |\UnitEnum| null $navigationGroup = 'Work';
    
    public function getWidgets(): array
    {
        return [
            Schedules::class,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('head_support') ?? false;
    }
}

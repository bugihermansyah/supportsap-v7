<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Support\Schedules;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class ScheduleDashboard extends BaseDashboard
{
    use HasPageShield;
    protected static ?string $title = 'Schedules';

    protected static string $routePath = 'schedule-dashboard';

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return '';
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string |\UnitEnum| null $navigationGroup = 'Work';
    
    public function getWidgets(): array
    {
        return [
            Schedules::class,
        ];
    }

}

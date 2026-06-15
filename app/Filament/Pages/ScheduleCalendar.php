<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CalendarWidget;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ScheduleCalendar extends Page
{

    protected static ?string $navigationLabel = 'Schedule Calendar';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string |\UnitEnum| null $navigationGroup = 'Work';

    protected static ?string $title = 'Schedule Calendar';

    protected string $view = 'filament.pages.schedule-calendar';

    protected function getHeaderWidgets(): array
    {
        return [
            CalendarWidget::class,
        ];
    }
}

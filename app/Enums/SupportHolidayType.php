<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportHolidayType: string implements HasLabel
{
    case PublicHoliday = 'public_holiday';
    case MajorHoliday = 'major_holiday';

    public function getLabel(): string
    {
        return match ($this) {
            self::PublicHoliday => 'Tanggal merah',
            self::MajorHoliday => 'Tanggal merah hari besar',
        };
    }
}

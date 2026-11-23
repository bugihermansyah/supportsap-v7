<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SupportIncentiveCategory: string implements HasLabel
{
    case Weekend = 'weekend';
    case PublicHoliday = 'public_holiday';
    case MajorHoliday = 'major_holiday';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekend => 'Sabtu/Minggu',
            self::PublicHoliday => 'Tanggal merah',
            self::MajorHoliday => 'Tanggal merah hari besar',
        };
    }
}

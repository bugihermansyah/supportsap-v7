<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OutstandingPriority: string implements HasColor, HasLabel
{
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
            default => self::Normal,
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::High => 'danger',
            self::Normal => 'primary',
            self::Low => 'gray',
        };
    }
}

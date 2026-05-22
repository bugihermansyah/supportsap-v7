<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LocationStatus: string implements HasColor, HasLabel, HasIcon
{
    case IMPLEMENTATION = 'implementation';
    case ACTIVE = 'active';
    case NON_ACTIVE = 'non_active';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMPLEMENTATION => 'Implementation',
            self::ACTIVE => 'Active',
            self::NON_ACTIVE => 'Non Active',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::IMPLEMENTATION => 'warning',
            self::ACTIVE => 'success',
            self::NON_ACTIVE => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::IMPLEMENTATION => 'heroicon-o-sparkles',
            self::ACTIVE => 'heroicon-o-check',
            self::NON_ACTIVE => 'heroicon-o-x-mark',
        };
    }
}

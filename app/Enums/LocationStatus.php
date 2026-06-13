<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LocationStatus: string implements HasColor, HasLabel, HasIcon
{
    case Implementation = 'implementation';
    case Active = 'active';
    case InActive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Implementation => 'Implementation',
            self::Active => 'Active',
            self::InActive => 'InActive',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Implementation => 'warning',
            self::Active => 'success',
            self::InActive => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Implementation => 'heroicon-o-sparkles',
            self::Active => 'heroicon-o-check',
            self::InActive => 'heroicon-o-x-mark',
        };
    }
}

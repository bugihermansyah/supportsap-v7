<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OutstandingStatus: string implements HasColor, HasLabel, HasIcon
{
    case Open = '0';
    case Close = '1';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Close => 'Close',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Open => 'danger',
            self::Close => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Open => 'heroicon-m-clock',
            self::Close => 'heroicon-m-check-circle',
        };
    }
}

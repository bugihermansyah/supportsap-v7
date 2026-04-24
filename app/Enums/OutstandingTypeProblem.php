<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OutstandingTypeProblem: string implements HasColor, HasLabel
{
    case NON = '3';
    case HW = '1';
    case SW = '2';
    case CIVIL = '4';

    public function getLabel(): string
    {
        return match ($this) {
            self::NON => 'H/W-Non',
            self::HW => 'H/W',
            self::SW => 'S/W',
            self::CIVIL => 'Sipil',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::NON => 'primary',
            self::HW => 'success',
            self::SW => 'info',
            self::CIVIL => 'warning',
        };
    }

    // public function getIcon(): ?string
    // {
    //     return match ($this) {
    //         self::Open => 'heroicon-m-exclamation-triangle',
    //         self::Close => 'heroicon-m-star',
    //     };
    // }
}

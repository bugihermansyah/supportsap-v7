<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
// use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LocationStatus: string implements HasColor, HasLabel
{
    case Implementasi = 'imple';
    case New = 'new';
    case Settle = 'settle';
    case Dismantle = 'dismantle';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Implementasi => 'Implementasi',
            self::New => 'New',
            self::Settle => 'Settle',
            self::Dismantle => 'Dismantle',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Implementasi => 'warning',
            self::New => 'info',
            self::Settle => 'success',
            self::Dismantle => 'warning',
            self::Cancelled => 'danger',
        };
    }

    // public function getIcon(): ?string
    // {
    //     return match ($this) {
    //         self::New => 'heroicon-m-sparkles',
    //         self::Processing => 'heroicon-m-arrow-path',
    //         self::Shipped => 'heroicon-m-truck',
    //         self::Delivered => 'heroicon-m-check-badge',
    //         self::Cancelled => 'heroicon-m-x-circle',
    //     };
    // }
}

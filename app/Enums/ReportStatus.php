<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasColor, HasLabel, HasIcon
{
    case Pending = '0';
    case Finish = '1';
    case PendingClient = '2';
    case Temporary = '3';
    case Monitoring = '4';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending SAP',
            self::Finish => 'Finish',
            self::PendingClient => 'Pending Client',
            self::Temporary => 'Temporary',
            self::Monitoring => 'Monitoring',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Pending => 'danger',
            self::Finish => 'success',
            self::PendingClient => 'warning',
            self::Temporary => 'primary',
            self::Monitoring => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-sparkles',
            self::Finish => 'heroicon-m-check-badge',
            self::PendingClient => 'heroicon-m-user',
            self::Temporary => 'heroicon-m-arrow-path',
            self::Monitoring => 'heroicon-m-eye',
        };
    }
}

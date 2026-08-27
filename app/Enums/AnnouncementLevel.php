<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AnnouncementLevel: string implements HasColor, HasIcon, HasLabel
{
    case Info = 'info';
    case Important = 'important';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Important => 'Penting',
            self::Urgent => 'Mendesak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Info => 'info',
            self::Important => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Info => 'heroicon-m-information-circle',
            self::Important => 'heroicon-m-exclamation-circle',
            self::Urgent => 'heroicon-m-exclamation-triangle',
        };
    }
}

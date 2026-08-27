<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tahap hidup sebuah baris `reportings` — terpisah dari ReportStatus yang
 * menyimpan hasil laporan (Pending SAP / Finish / dst).
 */
enum ReportingState: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Reported = 'reported';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Dijadwalkan',
            self::InProgress => 'Sedang dikerjakan',
            self::Reported => 'Sudah dilaporkan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::InProgress => 'warning',
            self::Reported => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Scheduled => 'heroicon-m-calendar-days',
            self::InProgress => 'heroicon-m-play-circle',
            self::Reported => 'heroicon-m-document-check',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }

    /** Tahap yang masih menunggu dikerjakan support. */
    public static function openStates(): array
    {
        return [self::Scheduled, self::InProgress];
    }
}

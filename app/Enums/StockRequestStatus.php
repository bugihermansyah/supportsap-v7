<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Alur request unit dari gudang internal support (warehouses.use_stock = 1).
 *
 * Berbeda dengan BorrowRequestStatus yang mengikuti alur logistik eksternal
 * (RP/SO/KRM, konfirmasi lewat email), alur ini hanya dua tahap stok:
 * Approved = stok dibooking, Released = unit fisik keluar gudang.
 */
enum StockRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Released = 'released';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Approved => 'Approved (Booked)',
            self::Released => 'Released',
            self::PartiallyReturned => 'Partially Returned',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved => 'warning',
            self::Released => 'primary',
            self::PartiallyReturned => 'warning',
            self::Returned => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Submitted => 'heroicon-m-paper-airplane',
            self::Approved => 'heroicon-m-lock-closed',
            self::Released => 'heroicon-m-truck',
            self::PartiallyReturned => 'heroicon-m-arrow-uturn-left',
            self::Returned => 'heroicon-m-check-badge',
            self::Rejected => 'heroicon-m-x-circle',
            self::Cancelled => 'heroicon-m-no-symbol',
        };
    }

    /** Stok sudah dibooking (qty_available berkurang) pada status-status ini. */
    public function holdsStock(): bool
    {
        return in_array($this, [self::Approved, self::Released, self::PartiallyReturned], true);
    }
}

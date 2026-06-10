<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BorrowRequestStatus: string implements HasColor, HasLabel, HasIcon
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case DeliveryScheduled = 'delivery_scheduled';
    case Delivered = 'delivered';
    case PickupScheduled = 'pickup_scheduled';
    case PickedUp = 'picked_up';
    case WaitingReturn = 'waiting_return';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Retur = 'retur';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::DeliveryScheduled => 'Delivery Scheduled',
            self::Delivered => 'Delivered',
            self::PickupScheduled => 'Pickup Scheduled',
            self::PickedUp => 'Picked Up',
            self::WaitingReturn => 'Waiting Return',
            self::PartiallyReturned => 'Partially Returned',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
            self::Retur => 'Retur',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::DeliveryScheduled => 'warning',
            self::Delivered => 'success',
            self::PickupScheduled => 'warning',
            self::PickedUp => 'success',
            self::WaitingReturn => 'warning',
            self::PartiallyReturned => 'warning',
            self::Returned => 'success',
            self::Cancelled => 'danger',
            self::Retur => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-document',
            self::Submitted => 'heroicon-m-paper-airplane',
            self::Approved => 'heroicon-m-check-circle',
            self::Rejected => 'heroicon-m-x-circle',
            self::DeliveryScheduled => 'heroicon-m-truck',
            self::Delivered => 'heroicon-m-cube',
            self::PickupScheduled => 'heroicon-m-arrow-path',
            self::PickedUp => 'heroicon-m-check',
            self::WaitingReturn => 'heroicon-m-arrow-uturn-left',
            self::PartiallyReturned => 'heroicon-m-arrow-path-rounded-square',
            self::Returned => 'heroicon-m-check-badge',
            self::Cancelled => 'heroicon-m-x-mark',
            self::Retur => 'heroicon-m-arrow-uturn-right',
        };
    }
}

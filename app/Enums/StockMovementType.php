<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mengikuti persis enum kolom stock_movements.movement_type di MySQL.
 */
enum StockMovementType: string implements HasColor, HasLabel
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Borrow = 'borrow';
    case Return = 'return';
    case AdjustmentPlus = 'adjustment_plus';
    case AdjustmentMinus = 'adjustment_minus';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
            self::Borrow => 'Booked (Borrow)',
            self::Return => 'Return',
            self::AdjustmentPlus => 'Adjustment +',
            self::AdjustmentMinus => 'Adjustment −',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::StockIn, self::AdjustmentPlus, self::TransferIn, self::Return => 'success',
            self::StockOut, self::AdjustmentMinus, self::TransferOut => 'danger',
            self::Borrow => 'warning',
        };
    }
}

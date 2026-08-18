<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku besar stok. Baris di sini tidak boleh diubah/dihapus —
 * koreksi dilakukan dengan movement baru (adjustment_plus / adjustment_minus).
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $unit_id
 * @property int|null $borrow_request_id
 * @property int|null $stock_request_id
 * @property StockMovementType $movement_type
 * @property int $qty
 * @property int $stock_before
 * @property int $stock_after
 * @property string|null $notes
 * @property string $created_by
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

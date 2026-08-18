<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_request_id
 * @property string $unit_id
 * @property int $qty
 * @property int $returned_qty
 * @property string|null $note
 * @property-read StockRequest|null $stockRequest
 * @property-read Unit|null $unit
 */
class StockRequestUnit extends Model
{
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function outstandingQty(): int
    {
        return max(0, (int) $this->qty - (int) $this->returned_qty);
    }
}

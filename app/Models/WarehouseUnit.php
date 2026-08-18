<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok satu unit di satu gudang.
 *
 * qty_total     = aset milik gudang (hanya berubah oleh stock in / adjustment)
 * qty_available = bebas dipakai request baru
 * qty_borrowed  = sudah dibooking/di lokasi pelanggan
 *
 * Invarian: qty_available + qty_borrowed = qty_total
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $unit_id
 * @property int $qty_total
 * @property int $qty_available
 * @property int $qty_borrowed
 * @property-read Warehouse|null $warehouse
 * @property-read Unit|null $unit
 */
class WarehouseUnit extends Model
{
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property bool $use_stock true = gudang internal support, stoknya dicatat
 */
class Warehouse extends Model
{
    protected function casts(): array
    {
        return [
            'use_stock' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(WarehouseUnit::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class);
    }

    /** Gudang internal yang stoknya dicatat (mis. Support HO). */
    public function scopeUsesStock(Builder $query): Builder
    {
        return $query->where('use_stock', 1);
    }
}

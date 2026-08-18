<?php

namespace App\Models;

use App\Enums\BorrowRequestType;
use App\Enums\StockRequestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kolom nyata tabel stock_requests (skema dikelola langsung di MySQL,
 * lihat database/sql/2026_08_18_stock_requests.sql).
 *
 * @property int $id
 * @property string $requester_id
 * @property int $warehouse_id
 * @property string $location_id
 * @property BorrowRequestType $request_type
 * @property StockRequestStatus $status
 * @property string|null $note
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $released_by
 * @property Carbon|null $released_at
 * @property-read Collection<int, StockRequestUnit> $units
 * @property-read Warehouse|null $warehouse
 * @property-read Location|null $location
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read User|null $releaser
 */
class StockRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
            'request_type' => BorrowRequestType::class,
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(StockRequestUnit::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isFullyReturned(): bool
    {
        return $this->units->every(fn (StockRequestUnit $unit) => $unit->returned_qty >= $unit->qty);
    }
}

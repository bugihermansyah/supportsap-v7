<?php

namespace App\Models;

use App\Enums\BorrowRequestStatus;
use App\Enums\LogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BorrowRequestStatus::class,
            'log_status' => BorrowRequestStatus::class,
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(BorrowRequestUnit::class);
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

    public function logs(): HasMany
    {
        return $this->hasMany(BorrowRequestLog::class);
    }
}

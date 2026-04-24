<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutstandingUnit extends Model
{
    use HasUlids;

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

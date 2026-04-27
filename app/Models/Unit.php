<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasUlids;

    public function outstandings(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Outstanding::class, 'outstanding_units', 'unit_id', 'outstanding_id')
            ->whereNull('outstanding_units.deleted_at');
    }
}

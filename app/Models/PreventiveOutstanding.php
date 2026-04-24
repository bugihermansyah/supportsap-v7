<?php

namespace App\Models;

use App\Enums\OutstandingPriority;
use App\Enums\OutstandingStatus;
use App\Enums\OutstandingTypeProblem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveOutstanding extends Model
{
    use HasUlids;

    protected $table = 'outstandings';

    protected function casts(): array
    {
        return [
            'status' => OutstandingStatus::class,
            'is_type_problem' => OutstandingTypeProblem::class,
            'priority' => OutstandingPriority::class,
            'lpm' => 'boolean',
            'is_implement' => 'boolean',
            'is_oncall' => 'boolean',
        ];
    }

    public function reportings(): HasMany
    {
        return $this->hasMany(Reporting::class, 'outstanding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'outstanding_units', 'outstanding_id', 'unit_id')
            ->withPivot('deleted_at');
    }

    public function outstandingUnits(): HasMany
    {
        return $this->hasMany(OutstandingUnit::class, 'outstanding_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

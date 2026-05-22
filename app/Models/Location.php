<?php

namespace App\Models;

use App\Enums\LocationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Location extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => LocationStatus::class,
            'email_to' => 'array',
            'email_cc' => 'array'
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function bd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bd_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} - " . ($this->company?->alias ?? 'N/A');
    }

    public function getAreaLabelAttribute(): string
    {
        return match ($this->area_status) {
            'in' => 'Dalam Kota',
            'out' => 'Luar Kota',
            default => '-',
        };
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_locations')
            ->withPivot('is_to');
    }

    public function customerLocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerLocation::class);
    }

    public function contracts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contract::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Reporting extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'email_to' => 'array',
            'email_cc' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reporting_users', 'reporting_id', 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function outstanding(): BelongsTo
    {
        return $this->belongsTo(Outstanding::class);
    }

    public function outstandingunits(): HasMany
    {
        return $this->hasMany(OutstandingUnit::class, 'outstanding_id', 'outstanding_id');
    }

    public function reportingUsers(): HasMany
    {
        return $this->hasMany(ReportingUser::class);
    }
    public function getMorphClass()
    {
        return SupportReporting::class;
    }
}

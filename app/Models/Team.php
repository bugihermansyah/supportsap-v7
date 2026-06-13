<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'email_to',
        'email_cc',
    ];

    protected $casts = [
        'email_to' => 'array',
        'email_cc' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

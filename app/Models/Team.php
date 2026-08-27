<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolom nyata tabel teams (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string $name
 * @property string|null $color
 * @property array|null $email_to
 * @property array|null $email_cc
 */
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

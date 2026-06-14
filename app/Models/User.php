<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasUserHelpers;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Tilto\Commentable\Contracts\Commenter;
use Tilto\Commentable\Traits\IsCommenter;

class User extends Authenticatable implements FilamentUser, Commenter
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUuids;
    use HasUserHelpers;
    use HasPushSubscriptions;

    use HasPanelShield;
    use HasRoles;
    use Notifiable;
    use IsCommenter;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->status != 0;
    }

}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasUserHelpers;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;
use Tilto\Commentable\Contracts\Commenter;
use Tilto\Commentable\Traits\IsCommenter;

/**
 * @property string $id
 * @property string $name
 * @property string|null $team_id
 * @property int $status
 */
class User extends Authenticatable implements Commenter, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPanelShield;
    use HasPushSubscriptions;
    use HasRoles;
    use HasUserHelpers;
    use HasUuids;
    use IsCommenter;
    use Notifiable;

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

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status != 0;
    }

    /**
     * Head-office support is a support role without a primary team.
     * Its visibility must therefore be determined by the role, not by
     * the nullable team_id column.
     */
    public function isSupportHo(): bool
    {
        return $this->hasRole('support_ho');
    }

    /**
     * True only for support users whose data must be limited to their team.
     */
    public function hasTeamScopedSupportRole(): bool
    {
        return $this->hasAnyRole(['support', 'head_support']) && ! $this->isSupportHo();
    }

    /**
     * Roles allowed to see support data across all teams.
     */
    public function canViewAllSupportTeams(): bool
    {
        return $this->hasAnyRole(['support_ho', 'helpdesk', 'admin', 'super_admin', 'owner']);
    }

    public function getAvatarUrlAttribute()
    {
        return null;
    }

    public function reportings(): BelongsToMany
    {
        return $this->belongsToMany(Reporting::class, 'reporting_users', 'user_id', 'reporting_id');
    }

    public function reportingUsers(): HasMany
    {
        return $this->hasMany(ReportingUser::class, 'user_id');
    }

    public function outstandings(): HasMany
    {
        return $this->hasMany(Outstanding::class, 'user_id');
    }

    public function borrowRequests(): HasMany
    {
        return $this->hasMany(BorrowRequest::class, 'requester_id');
    }
}

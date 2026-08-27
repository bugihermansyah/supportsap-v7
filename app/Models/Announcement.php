<?php

namespace App\Models;

use App\Enums\AnnouncementLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Pengumuman internal (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string $title
 * @property string $body
 * @property AnnouncementLevel $level
 * @property string|null $quiz_session_id
 * @property array|null $target_roles
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_published
 * @property bool $is_pinned
 * @property string|null $created_by
 * @property-read QuizSession|null $quizSession
 * @property-read User|null $creator
 * @property-read Collection<int, AnnouncementRead> $reads
 */
class Announcement extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'level' => AnnouncementLevel::class,
            'target_roles' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /** Pengumuman yang sedang tayang (published + dalam rentang tanggal). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Hanya pengumuman yang ditujukan untuk role user ini. */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $roles = $user->getRoleNames()->all();

        return $query->where(function (Builder $q) use ($roles): void {
            $q->whereNull('target_roles')
                ->orWhereJsonLength('target_roles', 0);

            foreach ($roles as $role) {
                $q->orWhereJsonContains('target_roles', $role);
            }
        });
    }

    public function isReadBy(?string $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->reads->contains(fn (AnnouncementRead $read) => $read->user_id === $userId);
    }

    /** Gambar pengumuman (opsional, collection "image"). */
    public function imageUrl(): ?string
    {
        return $this->getFirstMediaUrl('image') ?: null;
    }
}

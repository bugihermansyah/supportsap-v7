<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penanda "sudah dibaca" per user (skema dikelola langsung di MySQL).
 *
 * @property int $id
 * @property string $announcement_id
 * @property string $user_id
 * @property Carbon $read_at
 * @property-read Announcement|null $announcement
 * @property-read User|null $user
 */
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Filament\Widgets\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Ringkasan pengumuman di dashboard: hanya yang belum dibaca / disematkan,
 * maksimal 3 kartu, tampilan bertumpuk supaya enak dibaca di HP.
 */
class AnnouncementFeed extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.announcements.announcement-feed';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return Announcement::query()
            ->active()
            ->forUser($user)
            ->where(fn ($query) => $query
                ->where('is_pinned', true)
                ->orWhereDoesntHave('reads', fn ($read) => $read->where('user_id', $user->id)))
            ->exists();
    }

    /** Maksimal 3 pengumuman: belum dibaca / disematkan lebih dulu. */
    public function announcements(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return Announcement::query()
            ->active()
            ->forUser($user)
            ->with(['quizSession', 'reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->where(fn ($query) => $query
                ->where('is_pinned', true)
                ->orWhereDoesntHave('reads', fn ($read) => $read->where('user_id', $user->id)))
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
    }

    public function markRead(string $announcementId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcementId, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }
}

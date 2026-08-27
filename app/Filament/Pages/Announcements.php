<?php

namespace App\Filament\Pages;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Halaman baca pengumuman untuk semua user panel.
 * Tampilan dirancang mobile-first (kartu bertumpuk, tombol lebar penuh di HP).
 */
class Announcements extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Work';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $title = 'Pengumuman';

    protected static ?int $navigationSort = 0;

    /** Dibedakan dari route resource `announcements` milik pengelola. */
    protected static ?string $slug = 'pengumuman';

    protected string $view = 'filament.pages.announcements';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::unreadCountFor(auth()->user());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** Pengumuman yang tayang untuk user ini, disematkan dulu. */
    public function announcements(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return Announcement::query()
            ->active()
            ->forUser($user)
            ->with(['quizSession', 'creator', 'media', 'reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();
    }

    public function unreadCount(): int
    {
        return static::unreadCountFor(auth()->user());
    }

    public static function unreadCountFor(?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        return Announcement::query()
            ->active()
            ->forUser($user)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
            ->count();
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

    public function markAllReadAction(): Action
    {
        return Action::make('markAllRead')
            ->label('Tandai semua dibaca')
            ->icon('heroicon-m-check-circle')
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Tandai semua pengumuman sebagai dibaca?')
            ->modalDescription('Pengumuman tetap bisa dibuka lagi kapan saja.')
            ->modalSubmitActionLabel('Ya, tandai semua')
            ->modalCancelActionLabel('Batal')
            ->action(function (): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $unread = Announcement::query()
                    ->active()
                    ->forUser($user)
                    ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
                    ->pluck('id');

                foreach ($unread as $id) {
                    AnnouncementRead::query()->firstOrCreate(
                        ['announcement_id' => $id, 'user_id' => $user->id],
                        ['read_at' => now()],
                    );
                }

                Notification::make()
                    ->success()
                    ->title('Semua pengumuman ditandai sudah dibaca')
                    ->send();
            });
    }
}

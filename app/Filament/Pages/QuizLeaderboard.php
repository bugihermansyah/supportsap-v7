<?php

namespace App\Filament\Pages;

use App\Models\QuizAttempt;
use App\Models\QuizSession;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Papan peringkat quiz: per sesi (benar terbanyak, durasi tercepat)
 * dan rekap lintas sesi.
 */
class QuizLeaderboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Quiz';

    protected static ?string $navigationLabel = 'Leaderboard';

    protected static ?string $title = 'Leaderboard Quiz';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.quiz-leaderboard';

    /** ULID sesi, atau null untuk rekap semua sesi. */
    public ?string $sessionId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([
            'support', 'head_support', 'helpdesk', 'manager', 'admin', 'super_admin',
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $requested = request()->query('session');

        $this->sessionId = is_string($requested) && $requested !== '' ? $requested : null;
    }

    /** Pilihan sesi untuk dropdown. */
    public function sessionOptions(): Collection
    {
        return QuizSession::query()
            ->published()
            ->orderByDesc('starts_at')
            ->get();
    }

    public function currentSession(): ?QuizSession
    {
        return $this->sessionId === null
            ? null
            : QuizSession::query()->find($this->sessionId);
    }

    /** Peringkat satu sesi: benar terbanyak, lalu durasi tercepat. */
    public function sessionRanking(): Collection
    {
        if ($this->sessionId === null) {
            return collect();
        }

        return QuizAttempt::query()
            ->where('quiz_session_id', $this->sessionId)
            ->whereNotNull('finished_at')
            ->with('user')
            ->orderByDesc('correct_answers')
            ->orderBy('duration_seconds')
            ->get();
    }

    /** Rekap lintas sesi per peserta. */
    public function overallRanking(): Collection
    {
        return QuizAttempt::query()
            ->whereNotNull('finished_at')
            ->selectRaw('user_id')
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('SUM(correct_answers) as total_correct')
            ->selectRaw('SUM(total_questions) as total_questions_sum')
            ->selectRaw('SUM(duration_seconds) as total_duration')
            ->groupBy('user_id')
            ->with('user')
            ->orderByDesc('total_correct')
            ->orderBy('total_duration')
            ->get();
    }

    public static function formatDuration(?int $seconds): string
    {
        return QuizAttempt::formatDuration($seconds);
    }
}

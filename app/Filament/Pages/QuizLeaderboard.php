<?php

namespace App\Filament\Pages;

use App\Models\QuizAttempt;
use App\Models\QuizSession;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Papan peringkat quiz: per sesi (benar terbanyak, durasi tercepat)
 * dan rekap lintas sesi.
 */
class QuizLeaderboard extends Page
{
    use HasPageShield;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Quiz';

    protected static ?string $navigationLabel = 'Leaderboard';

    protected static ?string $title = 'Leaderboard Quiz';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.quiz-leaderboard';

    /** ULID sesi, atau null untuk rekap semua sesi. */
    public ?string $sessionId = null;

    private ?QuizSession $sessionCache = null;

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
            ->resultsVisible()
            ->orderByDesc('starts_at')
            ->get();
    }

    public function currentSession(): ?QuizSession
    {
        if ($this->sessionId === null) {
            return null;
        }

        if ($this->sessionCache?->id === $this->sessionId) {
            return $this->sessionCache;
        }

        return $this->sessionCache = QuizSession::query()->find($this->sessionId);
    }

    /** Peringkat sebuah sesi baru boleh dilihat setelah sesi itu ditutup. */
    public function resultsVisible(): bool
    {
        return (bool) $this->currentSession()?->resultsVisible();
    }

    /** Peringkat satu sesi: benar terbanyak, lalu durasi tercepat. */
    public function sessionRanking(): Collection
    {
        if ($this->sessionId === null || ! $this->resultsVisible()) {
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
            ->whereIn('quiz_session_id', QuizSession::query()->resultsVisible()->select('id'))
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

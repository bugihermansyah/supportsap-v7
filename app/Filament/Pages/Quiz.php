<?php

namespace App\Filament\Pages;

use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestionOption;
use App\Models\QuizSession;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Halaman peserta: daftar sesi quiz yang sedang dibuka, pengerjaan soal
 * (urutan diacak per peserta) dan hasil akhir.
 */
class Quiz extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Quiz';

    protected static ?string $navigationLabel = 'Ikuti Quiz';

    protected static ?string $title = 'Quiz';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.quiz';

    /** Attempt yang sedang dikerjakan atau sedang dilihat hasilnya. */
    public ?string $attemptId = null;

    /** Posisi soal yang sedang ditampilkan. */
    public int $index = 0;

    private ?QuizAttempt $attemptCache = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['support', 'head_support', 'super_admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    /** Sesi yang sedang dibuka, lengkap dengan attempt milik user (kalau ada). */
    public function sessions(): Collection
    {
        return QuizSession::query()
            ->available()
            ->withCount('questions')
            ->with(['attempts' => fn ($query) => $query->where('user_id', auth()->id())])
            ->orderBy('ends_at')
            ->get();
    }

    /** Riwayat pengerjaan peserta ini. */
    public function history(): Collection
    {
        return QuizAttempt::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('finished_at')
            ->with('session')
            ->orderByDesc('finished_at')
            ->limit(20)
            ->get();
    }

    public function attempt(): ?QuizAttempt
    {
        if ($this->attemptId === null) {
            return null;
        }

        if ($this->attemptCache?->id === $this->attemptId) {
            return $this->attemptCache;
        }

        return $this->attemptCache = QuizAttempt::query()
            ->where('user_id', auth()->id())
            ->with([
                'session',
                'answers.question.options',
                'answers.question.product',
                'answers.question.media',
            ])
            ->find($this->attemptId);
    }

    public function currentAnswer(): ?QuizAttemptAnswer
    {
        $answers = $this->attempt()?->answers;

        if ($answers === null) {
            return null;
        }

        return $answers->values()->get($this->index);
    }

    /** Hasil (skor + pembahasan) baru boleh dibuka setelah sesi quiz ditutup. */
    public function resultsVisible(?QuizAttempt $attempt = null): bool
    {
        $attempt ??= $this->attempt();

        return (bool) $attempt?->session?->resultsVisible();
    }

    /** Rekap benar/salah per kategori produk untuk halaman hasil. */
    public function categoryRecap(): Collection
    {
        $attempt = $this->attempt();

        if ($attempt === null) {
            return collect();
        }

        return $attempt->answers
            ->groupBy(function (QuizAttemptAnswer $answer): string {
                $category = $answer->question?->product?->name;

                return filled($category) ? $category : 'Tanpa kategori';
            })
            ->map(fn (Collection $answers, string $category) => [
                'category' => $category,
                'total' => $answers->count(),
                'correct' => $answers->where('is_correct', true)->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /** Konfirmasi mulai quiz (modal Filament, bukan confirm bawaan browser). */
    public function startAction(): Action
    {
        return Action::make('start')
            ->label('Mulai')
            ->size('sm')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-play-circle')
            ->modalHeading('Mulai quiz sekarang?')
            ->modalDescription('Kamu hanya punya satu kali kesempatan, dan durasi pengerjaan dihitung sejak sekarang.')
            ->modalSubmitActionLabel('Ya, mulai')
            ->modalCancelActionLabel('Batal')
            ->action(function (array $arguments): void {
                $this->start($arguments['session'] ?? '');
            });
    }

    /** Konfirmasi selesaikan quiz. */
    public function finishAction(): Action
    {
        return Action::make('finish')
            ->label('Selesaikan quiz')
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-circle')
            ->modalHeading('Selesaikan quiz?')
            ->modalDescription(function (): string {
                $answers = $this->attempt()?->answers;
                $unanswered = $answers === null
                    ? 0
                    : $answers->whereNull('quiz_question_option_id')->count();

                return $unanswered > 0
                    ? "Masih ada {$unanswered} soal yang belum dijawab dan akan dihitung salah. Jawaban tidak bisa diubah setelah ini."
                    : 'Semua soal sudah dijawab. Jawaban tidak bisa diubah setelah ini.';
            })
            ->modalSubmitActionLabel('Ya, selesaikan')
            ->modalCancelActionLabel('Kembali mengerjakan')
            ->action(fn () => $this->finish());
    }

    public function start(string $sessionId): void
    {
        $session = QuizSession::query()->with('questions')->find($sessionId);

        if ($session === null || ! $session->isAvailable()) {
            Notification::make()->danger()->title('Sesi tidak tersedia')->send();

            return;
        }

        if ($session->questions->isEmpty()) {
            Notification::make()->danger()->title('Sesi ini belum punya soal')->send();

            return;
        }

        $existing = QuizAttempt::query()
            ->where('quiz_session_id', $session->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            // Sudah pernah mulai: lanjutkan kalau belum selesai, kalau sudah tampilkan hasil.
            $this->openAttempt($existing);

            return;
        }

        try {
            $attempt = DB::transaction(function () use ($session): QuizAttempt {
                $questions = $session->questions->shuffle()->values();

                $attempt = QuizAttempt::create([
                    'quiz_session_id' => $session->id,
                    'user_id' => auth()->id(),
                    'started_at' => now(),
                    'total_questions' => $questions->count(),
                    'correct_answers' => 0,
                ]);

                foreach ($questions as $position => $question) {
                    QuizAttemptAnswer::create([
                        'quiz_attempt_id' => $attempt->id,
                        'quiz_question_id' => $question->id,
                        'sort' => $position,
                        'is_correct' => false,
                    ]);
                }

                return $attempt;
            });
        } catch (QueryException) {
            // Race condition pada unique (quiz_session_id, user_id).
            Notification::make()->danger()->title('Quiz ini sudah pernah kamu kerjakan')->send();

            return;
        }

        $this->openAttempt($attempt);
    }

    public function openAttempt(QuizAttempt|string $attempt): void
    {
        $attempt = $attempt instanceof QuizAttempt
            ? $attempt
            : QuizAttempt::query()->where('user_id', auth()->id())->find($attempt);

        if ($attempt === null) {
            return;
        }

        $this->attemptCache = null;
        $this->attemptId = $attempt->id;
        $this->index = 0;
    }

    public function backToList(): void
    {
        $this->attemptCache = null;
        $this->attemptId = null;
        $this->index = 0;
    }

    public function answer(string $answerId, string $optionId): void
    {
        $attempt = $this->attempt();

        if ($attempt === null || $attempt->isFinished()) {
            return;
        }

        if ($this->closeIfSessionEnded($attempt)) {
            return;
        }

        $answer = $attempt->answers->firstWhere('id', $answerId);

        if ($answer === null) {
            return;
        }

        $option = QuizQuestionOption::query()
            ->where('quiz_question_id', $answer->quiz_question_id)
            ->find($optionId);

        if ($option === null) {
            return;
        }

        $answer->update([
            'quiz_question_option_id' => $option->id,
            'is_correct' => $option->is_correct,
            'answered_at' => now(),
        ]);

        $this->attemptCache = null;
    }

    public function goTo(int $index): void
    {
        $total = $this->attempt()?->answers->count() ?? 0;

        $this->index = max(0, min($index, max($total - 1, 0)));
    }

    public function next(): void
    {
        $this->goTo($this->index + 1);
    }

    public function previous(): void
    {
        $this->goTo($this->index - 1);
    }

    public function finish(): void
    {
        $attempt = $this->attempt();

        if ($attempt === null || $attempt->isFinished()) {
            return;
        }

        $this->finishAttempt($attempt);

        Notification::make()
            ->success()
            ->title('Quiz selesai')
            ->body($this->resultsVisible($attempt)
                ? "Benar {$attempt->correct_answers} dari {$attempt->total_questions} soal."
                : 'Jawaban kamu sudah tersimpan. Hasil dan pembahasan dibuka setelah sesi ini ditutup.')
            ->send();
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /** Tutup otomatis kalau sesi keburu berakhir saat peserta masih mengerjakan. */
    private function closeIfSessionEnded(QuizAttempt $attempt): bool
    {
        if ($attempt->session === null || $attempt->session->ends_at->gte(now())) {
            return false;
        }

        $this->finishAttempt($attempt);

        Notification::make()
            ->warning()
            ->title('Sesi quiz sudah ditutup')
            ->body('Jawaban yang sudah kamu isi tetap tersimpan.')
            ->send();

        return true;
    }

    private function finishAttempt(QuizAttempt $attempt): void
    {
        $attempt->update([
            'finished_at' => now(),
            'duration_seconds' => (int) $attempt->started_at->diffInSeconds(now()),
            'correct_answers' => $attempt->answers->where('is_correct', true)->count(),
            'total_questions' => $attempt->answers->count(),
        ]);

        $this->attemptCache = null;
        $this->index = 0;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu kali pengerjaan quiz oleh seorang peserta (1 baris per user per sesi).
 * (skema dikelola langsung di MySQL)
 *
 * @property string $id
 * @property string $quiz_session_id
 * @property string $user_id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_seconds
 * @property int $total_questions
 * @property int $correct_answers
 * @property-read QuizSession|null $session
 * @property-read User|null $user
 * @property-read Collection<int, QuizAttemptAnswer> $answers
 */
class QuizAttempt extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Urutan soal milik peserta ini (sudah diacak saat mulai). */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class)->orderBy('sort');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** Nilai dalam persen, dibulatkan. */
    public function scorePercentage(): int
    {
        if ($this->total_questions < 1) {
            return 0;
        }

        return (int) round($this->correct_answers / $this->total_questions * 100);
    }

    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}

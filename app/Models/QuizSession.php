<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesi quiz: kumpulan soal terpilih yang tersedia pada rentang tanggal tertentu.
 * (skema dikelola langsung di MySQL)
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_published
 * @property string|null $created_by
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
class QuizSession extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(QuizQuestion::class, 'quiz_session_questions')
            ->withPivot('sort')
            ->orderBy('quiz_session_questions.sort');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Sesi yang sedang dibuka saat ini. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function isAvailable(): bool
    {
        return $this->is_published
            && $this->starts_at->lte(now())
            && $this->ends_at->gte(now());
    }

    /** @return array{label: string, color: string} */
    public function statusBadge(): array
    {
        return match (true) {
            ! $this->is_published => ['label' => 'Draft', 'color' => 'gray'],
            $this->starts_at->gt(now()) => ['label' => 'Belum dibuka', 'color' => 'info'],
            $this->ends_at->lt(now()) => ['label' => 'Ditutup', 'color' => 'danger'],
            default => ['label' => 'Dibuka', 'color' => 'success'],
        };
    }
}

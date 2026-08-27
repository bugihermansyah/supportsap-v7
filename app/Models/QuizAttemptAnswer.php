<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jawaban peserta atas satu soal; `sort` menyimpan urutan acak milik peserta.
 * (skema dikelola langsung di MySQL)
 *
 * @property string $id
 * @property string $quiz_attempt_id
 * @property string $quiz_question_id
 * @property string|null $quiz_question_option_id
 * @property bool $is_correct
 * @property int $sort
 * @property Carbon|null $answered_at
 * @property-read QuizAttempt|null $attempt
 * @property-read QuizQuestion|null $question
 * @property-read QuizQuestionOption|null $option
 */
class QuizAttemptAnswer extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionOption::class, 'quiz_question_option_id');
    }
}

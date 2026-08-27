<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pilihan jawaban sebuah soal (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string $quiz_question_id
 * @property string $option_text
 * @property bool $is_correct
 * @property int $sort
 */
class QuizQuestionOption extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}

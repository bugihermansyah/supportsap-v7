<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Bank soal quiz (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string|null $product_id
 * @property string $question
 * @property string|null $explanation
 * @property bool $is_active
 * @property string|null $created_by
 * @property-read Product|null $product
 * @property-read Collection<int, QuizQuestionOption> $options
 */
class QuizQuestion extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class)->orderBy('sort');
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(QuizSession::class, 'quiz_session_questions')
            ->withPivot('sort');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Gambar soal (opsional, collection "image"). */
    public function imageUrl(): ?string
    {
        return $this->getFirstMediaUrl('image') ?: null;
    }
}

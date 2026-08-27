<?php

namespace App\Models;

use App\Enums\OutstandingPriority;
use App\Enums\OutstandingStatus;
use App\Enums\OutstandingTypeProblem;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tilto\Commentable\Contracts\Commentable;
use Tilto\Commentable\Traits\HasComments;

/**
 * Kolom nyata tabel outstandings (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string|null $number
 * @property string $location_id
 * @property string|null $product_id
 * @property string|null $team_id
 * @property string|null $title
 * @property int $level 1=Very Easy .. 5=Very Hard
 * @property string|null $reporter
 * @property string|null $reporter_name
 * @property string|null $date_in
 * @property string|null $date_visit
 * @property string|null $date_finish
 * @property string|null $date_temporary
 * @property bool|null $lpm
 * @property bool $is_implement
 * @property OutstandingTypeProblem $is_type_problem
 * @property bool $is_oncall
 * @property int $is_temporary
 * @property OutstandingPriority $priority
 * @property OutstandingStatus $status
 * @property string|null $user_id
 * @property string|null $note
 * @property string|null $create_user_id
 * @property-read Location|null $location
 * @property-read Product|null $product
 * @property-read Collection<int, Reporting> $reportings
 */
class Outstanding extends Model implements Commentable
{
    use HasComments;
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => OutstandingStatus::class,
            'is_type_problem' => OutstandingTypeProblem::class,
            'priority' => OutstandingPriority::class,
            'lpm' => 'boolean',
            'is_implement' => 'boolean',
            'is_oncall' => 'boolean',
        ];
    }

    public function reportings(): HasMany
    {
        return $this->hasMany(Reporting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'outstanding_units', 'outstanding_id', 'unit_id')
            ->withPivot('deleted_at');
    }

    public function outstandingUnits(): HasMany
    {
        return $this->hasMany(OutstandingUnit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected static function booted()
    {
        static::deleting(function (Outstanding $outstanding) {
            // Delete reportings individually to trigger their own deleting events
            // which will handle media deletion (via Spatie) and reporting_users deletion
            $outstanding->reportings->each(function ($reporting) {
                $reporting->delete();
            });

            // Delete outstanding units
            $outstanding->outstandingUnits()->delete();

            // Delete related comments if available
            if (method_exists($outstanding, 'comments')) {
                $outstanding->comments()->delete();
            }
        });
    }

    public function getCommentMentionProviders(): ?array
    {
        return [
            MentionProvider::make('@')
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('name', 'like', "%{$search}%")
                    ->orderBy('name')
                    ->limit(10)
                    ->pluck('name', 'id')
                    ->all())
                ->getLabelsUsing(fn (array $ids): array => User::query()
                    ->whereIn('id', $ids)
                    ->pluck('name', 'id')
                    ->all()),
            // MentionProvider::make('#')
            //     ->items([
            //         1 => 'How to Bake Bread',
            //         2 => 'Laravel Tips & Tricks',
            //         3 => 'The Future of PHP',
            //         4 => '10 Best Coding Practices',
            //         5 => 'Debugging 101',
            //         6 => 'Deploying with Docker',
            //     ]),
        ];
    }
}

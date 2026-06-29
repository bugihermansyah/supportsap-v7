<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Reporting extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'email_to' => 'array',
            'email_cc' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reporting_users', 'reporting_id', 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function outstanding(): BelongsTo
    {
        return $this->belongsTo(Outstanding::class);
    }

    public function outstandingunits(): HasMany
    {
        return $this->hasMany(OutstandingUnit::class, 'outstanding_id', 'outstanding_id');
    }

    public function reportingUsers(): HasMany
    {
        return $this->hasMany(ReportingUser::class);
    }

    public function getLocationTitleAttribute(): string
    {
        return "{$this->outstanding?->location?->name} - " . ($this->outstanding?->title);
    }

    protected static function booted()
    {
        static::deleting(function (Reporting $reporting) {
            $reporting->reportingUsers()->delete();
        });

        static::creating(function (Reporting $reporting) {
            if ($reporting->score === null) {
                $reporting->score = $reporting->calculateAutoScore();
            }
        });

        static::updating(function (Reporting $reporting) {
            // If score is explicitly set to null (meaning user cleared it), or if it's somehow null, recalculate it
            if ($reporting->score === null) {
                $reporting->score = $reporting->calculateAutoScore();
            }
        });
    }

    public static function getScoreGrade(int $score): string
    {
        $aPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 100);
        $a     = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $b     = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $c     = (int) safe_db_config('general.kpi_grade_c_min', 50);

        return match (true) {
            $score > $aPlus => 'A+',
            $score > $a     => 'A',
            $score > $b     => 'B',
            $score > $c     => 'C',
            default         => 'D',
        };
    }

    public static function getScoreColor(int $score): string
    {
        $aPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 100);
        $a     = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $b     = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $c     = (int) safe_db_config('general.kpi_grade_c_min', 50);

        return match (true) {
            $score === $aPlus => 'success',
            $score === $a     => 'success',
            $score === $b     => 'info',
            $score === $c     => 'warning',
            default         => 'danger',
        };
    }

    /**
     * Calculate automatic score based on reporting timeliness and completeness.
     */
    public function calculateAutoScore(): int
    {
        // If this is an HO visit, return a default perfect score of 100 without penalties
        if ($this->outstanding?->location?->is_ho) {
            return 100;
        }

        if (!$this->status) {
            return 0;
        }

        // Read configurable parameters from General Settings
        $baseScore = (int) safe_db_config('general.kpi_base_score', 100);
        $latePenaltyH1 = (int) safe_db_config('general.kpi_late_penalty_h1', 10);
        $latePenaltyH2 = (int) safe_db_config('general.kpi_late_penalty_h2', 20);
        $latePenaltyH3 = (int) safe_db_config('general.kpi_late_penalty_h3', 50);
        $noPhotoPenalty = (int) safe_db_config('general.kpi_no_photo_penalty', 15);
        $noFormPenalty = (int) safe_db_config('general.kpi_no_form_penalty', 30);
        $samedayBonus = (int) safe_db_config('general.kpi_sameday_bonus', 15);
        $bonusVeryHard = (int) safe_db_config('general.kpi_bonus_very_hard', 15);
        $bonusHard = (int) safe_db_config('general.kpi_bonus_hard', 10);

        $score = $baseScore;

        // Get outstanding level (1=Very Easy, 2=Easy, 3=Normal, 4=Hard, 5=Very Hard)
        $level = $this->outstanding?->level ?? 3;

        // 1. Penalty keterlambatan lapor
        $graceDays = $level >= 4 ? 1 : 0; // Hard/Very Hard gets 1 day tolerance

        $visitDate = \Carbon\Carbon::parse($this->date_visit ?? now())->startOfDay();
        $inputDate = $this->created_at ? $this->created_at->startOfDay() : now()->startOfDay();

        if ($inputDate->gt($visitDate)) {
            $daysLate = $inputDate->diffInDays($visitDate);
            $effectiveLate = max(0, $daysLate - $graceDays);
            
            if ($effectiveLate >= 3) {
                $score -= $latePenaltyH3;
            } elseif ($effectiveLate == 2) {
                $score -= $latePenaltyH2;
            } elseif ($effectiveLate == 1) {
                $score -= $latePenaltyH1;
            }
        }

        // 2. Kelengkapan Laporan (Foto dan Form Support)
        $hasPhoto = $this->getMedia('attachments')->count() > 0;
        if (!$hasPhoto) {
            $score -= $noPhotoPenalty;
        }

        $hasForm = $this->getMedia('form_support')->count() > 0;
        if (!$hasForm) {
            $score -= $noFormPenalty;
        }

        // 3. Progress Hari H (Dikerjakan di hari yang sama dengan jadwal info date)
        $outstandingDateIn = $this->outstanding?->date_in ? \Carbon\Carbon::parse($this->outstanding->date_in)->startOfDay() : null;
        if ($outstandingDateIn && $visitDate->eq($outstandingDateIn)) {
            $score += $samedayBonus;
        }

        // 4. Bonus berdasarkan tingkat kesulitan pekerjaan
        $levelBonus = match ($level) {
            5 => $bonusVeryHard,
            4 => $bonusHard,
            default => 0,
        };
        $score += $levelBonus;

        return max(0, $score);
    }
}

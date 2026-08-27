<?php

namespace App\Models;

use App\Enums\PendingReason;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use Carbon\Carbon;
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
 * Kolom nyata tabel reportings (skema dikelola langsung di MySQL).
 *
 * @property string $id
 * @property string $outstanding_id
 * @property string|null $cause
 * @property string|null $action
 * @property string|null $solution
 * @property string $work
 * @property string $date_visit
 * @property ReportStatus|null $status hasil laporan; null selama belum dilaporkan
 * @property PendingReason|null $pending_reason wajib saat status Pending SAP / Pending Client
 * @property ReportingState $state tahap hidup baris ini
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $cancelled_by
 * @property string|null $revisit
 * @property string|null $note
 * @property string|null $signature
 * @property \Illuminate\Support\Carbon|null $start_work
 * @property \Illuminate\Support\Carbon|null $end_work
 * @property \Illuminate\Support\Carbon|null $send_mail_at
 * @property \Illuminate\Support\Carbon|null $user_created_at
 * @property array|null $email_to
 * @property array|null $email_cc
 * @property int|null $score null selama baris ini masih berupa jadwal
 * @property string|null $evaluation_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Outstanding|null $outstanding
 * @property-read Collection<int, User> $users
 * @property-read string $location_title
 */
class Reporting extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    /** Baris baru selalu lahir sebagai jadwal. */
    protected $attributes = [
        'state' => 'scheduled',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'pending_reason' => PendingReason::class,
            'state' => ReportingState::class,
            'email_to' => 'array',
            'email_cc' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reporting_users', 'reporting_id', 'user_id');
    }

    // Relasi user() dihapus: teknisi selalu dibaca dari pivot reporting_users
    // lewat users() / reportingUsers(). Kolom reportings.user_id dipensiunkan
    // (lihat database/sql/2026_08_27_reportings_drop_user_id.sql).

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
        return "{$this->outstanding?->location?->name} - ".($this->outstanding?->title);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Jadwal yang masih menunggu dikerjakan (belum dilaporkan, belum dibatalkan). */
    public function scopeOpenSchedule(Builder $query): Builder
    {
        return $query->whereIn('state', [ReportingState::Scheduled, ReportingState::InProgress]);
    }

    public function scopeReported(Builder $query): Builder
    {
        return $query->where('state', ReportingState::Reported);
    }

    /**
     * Batalkan jadwal: barisnya tetap tersimpan sebagai riwayat, tapi keluar dari
     * daftar kerja support dan tidak ikut dinilai KPI.
     */
    public function cancel(string $reason, ?string $userId = null): void
    {
        $this->forceFill([
            'state' => ReportingState::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancelled_by' => $userId ?? auth()->id(),
            'score' => null,
        ])->save();
    }

    protected static function booted()
    {
        static::deleting(function (Reporting $reporting) {
            $reporting->reportingUsers()->delete();
        });

        // `state` selalu diturunkan dari data, jadi tidak ada tempat lain yang perlu
        // mengisinya manual (kecuali pembatalan, lihat cancel()).
        static::saving(function (Reporting $reporting) {
            // Alasan pending hanya berlaku untuk Pending SAP / Pending Client.
            if (! PendingReason::isRequiredFor($reporting->status)) {
                $reporting->pending_reason = null;
            }

            $reporting->state = match (true) {
                $reporting->status !== null => ReportingState::Reported,
                $reporting->state === ReportingState::Cancelled => ReportingState::Cancelled,
                $reporting->start_work !== null => ReportingState::InProgress,
                default => ReportingState::Scheduled,
            };
        });

        static::creating(function (Reporting $reporting) {
            if ($reporting->score === null) {
                $reporting->score = $reporting->calculateAutoScore();
            }
        });

        static::updating(function (Reporting $reporting) {
            // Recalculate score on update, unless it was explicitly modified (e.g. manual evaluation)
            if (! $reporting->isDirty('score')) {
                $reporting->score = $reporting->calculateAutoScore();
            }
        });
    }

    public static function getScoreGrade(int $score): string
    {
        $aPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 100);
        $a = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $b = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $c = (int) safe_db_config('general.kpi_grade_c_min', 50);

        return match (true) {
            $score > $aPlus => 'A+',
            $score > $a => 'A',
            $score > $b => 'B',
            $score > $c => 'C',
            default => 'D',
        };
    }

    public static function getScoreColor(int $score): string
    {
        $aPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 100);
        $a = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $b = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $c = (int) safe_db_config('general.kpi_grade_c_min', 50);

        return match (true) {
            $score === $aPlus => 'success',
            $score === $a => 'success',
            $score === $b => 'info',
            $score === $c => 'warning',
            default => 'danger',
        };
    }

    /**
     * Skor KPI baris ini, atau null bila baris ini belum berupa laporan.
     *
     * Baris tanpa `status` masih berupa jadwal kunjungan: belum ada pekerjaan yang
     * bisa dinilai, jadi skornya null (bukan 0) supaya `avg('score')` di halaman KPI
     * mengabaikannya, bukan menariknya turun.
     */
    public function calculateAutoScore(): ?int
    {
        if (! $this->status) {
            return null;
        }

        // If this is an HO visit, return a default perfect score of 100 without penalties
        if ($this->outstanding?->location?->is_ho) {
            return 100;
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
        $level = $this->outstanding !== null ? $this->outstanding->level : 3;

        // 1. Penalty keterlambatan lapor
        $graceDays = $level >= 4 ? 1 : 0; // Hard/Very Hard gets 1 day tolerance

        $visitDate = Carbon::parse($this->date_visit ?? now())->startOfDay();
        $inputDate = $this->end_work ? Carbon::parse($this->end_work)->startOfDay() : ($this->updated_at ? $this->updated_at->startOfDay() : now()->startOfDay());

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
        if (! $hasPhoto) {
            $score -= $noPhotoPenalty;
        }

        $hasForm = $this->getMedia('form_support')->count() > 0;
        if (! $hasForm) {
            $score -= $noFormPenalty;
        }

        // 3. Progress Hari H (Dikerjakan di hari yang sama dengan jadwal info date)
        $outstandingDateIn = $this->outstanding?->date_in ? Carbon::parse($this->outstanding->date_in)->startOfDay() : null;
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

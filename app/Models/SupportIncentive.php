<?php

namespace App\Models;

use App\Enums\SupportIncentiveCategory;
use App\Enums\SupportIncentiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportIncentive extends Model
{
    public const ELIGIBLE_SUPPORT_ROLES = ['support', 'support_ho', 'head_support'];

    protected $fillable = [
        'support_user_id',
        'work_date',
        'category',
        'holiday_name',
        'rate',
        'reporting_id',
        'status',
        'verified_by',
        'verified_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'category' => SupportIncentiveCategory::class,
            'status' => SupportIncentiveStatus::class,
            'rate' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function supportUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }

    public function reporting(): BelongsTo
    {
        return $this->belongsTo(Reporting::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

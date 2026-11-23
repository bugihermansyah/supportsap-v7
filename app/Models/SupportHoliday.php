<?php

namespace App\Models;

use App\Enums\SupportHolidayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'category',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'category' => SupportHolidayType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

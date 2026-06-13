<?php

namespace App\Models;

use App\Enums\BorrowRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowRequestLog extends Model
{
    protected function casts(): array
    {
        return [
            'action' => BorrowRequestStatus::class,
            'is_claim' => 'boolean',
        ];
    }

    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function actionAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    protected $casts = [
        'details' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (BorrowRequestLog $log) {
            if ($log->borrowRequest) {
                // We use $log->action because earlier the user logged 'action' => 'submitted'
                // We also check for $log->status in case the column is actually named status.
                $ignoredActions = ['delivery_scheduled', 'delivered', 'pickup_scheduled', 'picked_up'];
                
                if (!in_array($log->action, $ignoredActions)) {
                    $log->borrowRequest->status = $log->action ?? $log->status;
                    $log->borrowRequest->save();
                }
            }
        });
    }
}

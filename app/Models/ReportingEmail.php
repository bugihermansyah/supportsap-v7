<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ReportingEmail extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'email_to' => 'array',
            'email_cc' => 'array',
            'start_work' => 'datetime',
            'end_work' => 'datetime',
            'send_mail_at' => 'datetime',
        ];
    }

    public function reporting(): BelongsTo
    {
        return $this->belongsTo(Reporting::class);
    }
}

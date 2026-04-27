<?php

namespace App\Jobs;

use App\Mail\ScheduleMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class ScheduleMailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $supportEmails,
        public string $dateVisit,
        public string $companyAlias,
        public string $locationName,
        public string $title,
        public string $reporter,
        public string $reporterName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!empty($this->supportEmails)) {
            Mail::to($this->supportEmails)->send(
                new ScheduleMail(
                    $this->dateVisit,
                    $this->companyAlias,
                    $this->locationName,
                    $this->title,
                    $this->reporter,
                    $this->reporterName
                )
            );
        }
    }
}

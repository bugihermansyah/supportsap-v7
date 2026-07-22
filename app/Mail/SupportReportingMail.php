<?php

namespace App\Mail;

use App\Models\Reporting;
use App\Models\ReportingEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReportingMail extends Mailable
{
    use Queueable, SerializesModels;

    public Reporting $reporting;
    public ?ReportingEmail $reportingEmail = null;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Reporting|ReportingEmail $mailData,
        public bool $isInternal = true,
    ) {
        if ($mailData instanceof ReportingEmail) {
            $this->reportingEmail = $mailData;
            $this->reporting = $mailData->reporting;
        } else {
            $this->reporting = $mailData;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $company = $this->reporting->outstanding?->location?->company?->alias ?? '-';
        $location = $this->reporting->outstanding?->location?->name ?? '-';
        $title = $this->reporting->outstanding?->title ?? '-';
        $status = $this->reporting->status?->getLabel() ?? '-';

        $prefix = $this->isInternal ? '[Internal]' : '[Support SAP]';

        return new Envelope(
            subject: "{$prefix} {$company} - {$location} : {$status} {$title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support-reporting',
            with: [
                'reportingEmail' => $this->reportingEmail,
                'reporting' => $this->reporting,
                'outstanding' => $this->reporting->outstanding,
                'location' => $this->reporting->outstanding?->location,
                'team' => $this->reporting->outstanding?->location?->team,
                'users' => $this->reporting->users,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $mediaCollection = $this->reportingEmail
            ? $this->reportingEmail->getMedia('attachments')
            : $this->reporting->getMedia('attachments');

        return $mediaCollection
            ->map(fn ($media) => Attachment::fromPath($media->getPath())
                ->as($media->file_name)
                ->withMime($media->mime_type)
            )
            ->toArray();
    }
}

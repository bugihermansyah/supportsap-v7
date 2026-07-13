<?php

namespace App\Mail;

use App\Models\Reporting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReportingMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Reporting $reporting,
        public bool $isInternal = true,
        public bool $excludeWorkTime = true,
    ) {}

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
                'reporting' => $this->reporting,
                'outstanding' => $this->reporting->outstanding,
                'location' => $this->reporting->outstanding?->location,
                'team' => $this->reporting->outstanding?->location?->team,
                'users' => $this->reporting->users,
                'excludeWorkTime' => $this->excludeWorkTime,
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
        return $this->reporting
            ->getMedia('attachments')
            ->map(fn ($media) => Attachment::fromPath($media->getPath())
                ->as($media->file_name)
                ->withMime($media->mime_type)
            )
            ->toArray();
    }
}

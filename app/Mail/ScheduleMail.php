<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $dateVisit,
        public string $companyAlias,
        public string $locationName,
        public string $title,
        public string $reporter,
        public string $reporterName
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Schedule] {$this->companyAlias} - {$this->locationName} : {$this->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.schedule-mail',
            with: [
                'dateVisit' => $this->dateVisit,
                'companyAlias' => $this->companyAlias,
                'locationName' => $this->locationName,
                'title' => $this->title,
                'reporter' => $this->reporter,
                'reporterName' => $this->reporterName,
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
        return [];
    }
}

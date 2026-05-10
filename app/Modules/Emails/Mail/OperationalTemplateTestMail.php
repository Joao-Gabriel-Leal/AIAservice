<?php

namespace App\Modules\Emails\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationalTemplateTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Teste] '.$this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operational-template',
            with: [
                'html' => $this->htmlContent,
                'subject' => $this->subjectLine,
            ],
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->summary['workspace']} weekly summary: {$this->summary['period']}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.workspace-summary');
    }
}

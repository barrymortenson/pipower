<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PowerEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $eventType,
        public string $eventSubject,
        public string $message,
        public string $hostname
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->hostname),
            subject: "{$this->hostname}: {$this->eventSubject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.power-event',
            with: [
                'eventType' => $this->eventType,
                'eventSubject' => $this->eventSubject,
                'message' => $this->message,
                'hostname' => $this->hostname,
            ],
        );
    }
}

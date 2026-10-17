<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $purpose = 'registration')
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your SpaceMatch Verification Code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: ['code' => $this->code, 'purpose' => $this->purpose],
        );
    }
}
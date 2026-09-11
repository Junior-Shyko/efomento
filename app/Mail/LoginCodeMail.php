<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu código de acesso ao e-fomento');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-code');
    }
}

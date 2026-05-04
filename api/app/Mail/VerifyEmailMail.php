<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $frontendUrl;

    public function __construct(
        public readonly User   $user,
        public readonly string $token,
    ) {
        $this->frontendUrl = rtrim(config('app.frontend_url', env('APP_FRONTEND_URL', 'http://localhost:3000')), '/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your SukiCart Email Address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
        );
    }
}

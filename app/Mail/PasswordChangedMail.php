<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $appName;
    public $time;
    public $ip;

    public function __construct($username, $ip = null)
    {
        $this->username = $username;
        $this->appName = config('app.name');
        $this->time = now()->format('Y-m-d H:i:s');
        $this->ip = $ip ?? request()->ip();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password has been changed - ' . $this->appName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

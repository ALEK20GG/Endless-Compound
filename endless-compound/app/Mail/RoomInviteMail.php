<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoomInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $inviterName,
        public readonly string $roomName,
        public readonly string $roomCode,
        public readonly string $joinUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} invited you to play Endless Compound! ⚗️",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.room-invite',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class DelayedReviewReturnMail extends Mailable
{
    public function __construct(private readonly string $homeUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A review is ready in Global Spoken English',
        );
    }

    public function content(): Content
    {
        $homeUrl = e($this->homeUrl);

        return new Content(
            htmlString: '<p>A delayed review is ready.</p><p><a href="'.$homeUrl.'">Open Home</a></p>',
        );
    }
}

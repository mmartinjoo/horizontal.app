<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Services\Url;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Invitation $invitation,
        string $plainToken
    ) {
        $this->acceptUrl = Url::createInvitationAcceptanceUrl(
            tenancy()->tenant,
            $plainToken
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to Horizontal',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Models\AdminInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invites someone to help run the admin, in the club's official look rather than an event's. The links hold the
 * invitation's secret and travel through the queue with the email, so the queued job is encrypted.
 */
class AdminInvitationMail extends Mailable implements ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * The official look: resources/views/vendor/mail/html/themes/official.css.
     *
     * @var string
     */
    public $theme = 'official';

    public function __construct(
        public AdminInvitation $invitation,
        public string $acceptUrl,
        public string $declineUrl,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('lri.site.name')),
            subject: 'You’re invited to the '.config('lri.site.name').' admin',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-invitation',
            with: [
                'inviter' => $this->invitation->inviterName(),
                'email' => $this->invitation->email,
                'expiry' => $this->invitation->readableExpiry(),
                'details' => [
                    $this->invitation->roles->count() === 1 ? 'Your role' : 'Your roles' => $this->invitation->roleNames(),
                    'Your login' => $this->invitation->email,
                    'Link expires' => $this->invitation->readableExpiry(),
                ],
            ],
        );
    }
}

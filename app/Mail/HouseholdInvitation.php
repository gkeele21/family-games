<?php

namespace App\Mail;

use App\Models\Scorekeeper\HouseholdInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HouseholdInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HouseholdInvite $invite)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invite->household->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.household_invitation',
            with: [
                'household' => $this->invite->household,
                'inviter'   => $this->invite->invitedBy,
                'invite'    => $this->invite,
                'acceptUrl' => route('scorekeeper.invites.show', $this->invite->token),
            ],
        );
    }
}

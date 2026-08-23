<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** L'email d'invitation à rejoindre une organisation. */
class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        // `organization_account_id` est NOT NULL en `cascadeOnDelete` : si la société disparaît,
        // l'invitation aussi. Contrairement à l'expéditeur d'un message, elle est donc toujours là.
        $societe = $this->invitation->organization->name;

        return new Envelope(
            subject: "Vous êtes invité à rejoindre {$societe}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization-invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => $this->acceptUrl,
                'societe' => $this->invitation->organization->name,
            ],
        );
    }
}

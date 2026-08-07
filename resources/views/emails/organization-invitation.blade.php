@component('mail::message')
# Vous êtes invité à rejoindre {{ $societe }}

{{ $invitation->inviter?->name ?? 'Un responsable' }} vous invite à rejoindre l'équipe
**{{ $societe }}** sur Brio.

@component('mail::button', ['url' => $acceptUrl])
Accepter l'invitation
@endcomponent

@if ($invitation->expires_at)
Cette invitation expire le {{ $invitation->expires_at->translatedFormat('d F Y') }}.
@endif

Si vous n'attendiez pas cette invitation, vous pouvez ignorer cet email.

@endcomponent

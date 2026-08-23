<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Services\Organizations\OrganizationMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Acceptation d'une invitation reçue par email. */
class OrganizationInvitationController extends Controller
{
    public function accept(string $token, OrganizationMembershipService $adhesions): RedirectResponse
    {
        $invitation = OrganizationInvitation::where('token', $token)->first();

        // Jeton inconnu, déjà consommé ou périmé : on ne dit pas lequel des trois.
        abort_if($invitation === null || ! $invitation->estUtilisable(), 403);

        $utilisateur = Auth::user();

        // L'invitation est nominative.
        $emailConnecte = mb_strtolower((string) $utilisateur->email);
        $emailInvite = mb_strtolower((string) $invitation->email);

        abort_unless($emailConnecte === $emailInvite, 403);

        $organisation = $invitation->organization;
        abort_if($organisation === null, 404);

        $adhesions->rattacher(
            $organisation,
            $utilisateur,
            $invitation->role,
            $invitation->invited_by,
        );

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', "Vous avez rejoint {$organisation->name}.");
    }
}

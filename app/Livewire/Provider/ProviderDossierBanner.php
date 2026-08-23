<?php

namespace App\Livewire\Provider;

use App\Services\Onboarding\ProviderDossierSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** « VOTRE COMPTE N'EST PAS ENCORE VALIDÉ, ET VOICI CE QU'IL RESTE À FAIRE. */
class ProviderDossierBanner extends Component
{
    /** Au-delà, la liste devient un mur de texte qu'on ne lit plus. L'assistant a le détail. */
    private const MANQUANTS_AFFICHES = 3;

    public function render(): View
    {
        $user = Auth::user();

        $verifie = $user?->providerProfile?->verification_status === 'verified';

        // UN COMPTE VÉRIFIÉ NE VOIT RIEN.
        if (! $user || $verifie) {
            return view('livewire.provider.provider-dossier-banner', [
                'afficher' => false,
                'manquants' => [],
                'reste' => 0,
                'enRelecture' => false,
            ]);
        }

        $dossier = app(ProviderDossierSummary::class)->for($user);
        $manquants = array_values($dossier['blockers']);

        return view('livewire.provider.provider-dossier-banner', [
            'afficher' => true,
            // `blockers` est ce que le PRESTATAIRE n'a pas fait — la seule liste sur laquelle il
            // peut agir. Les `warnings` concernent l'administration : les lui montrer lui donnerait
            // du travail qui ne lui appartient pas.
            'manquants' => array_slice($manquants, 0, self::MANQUANTS_AFFICHES),
            'reste' => max(0, count($manquants) - self::MANQUANTS_AFFICHES),
            'enRelecture' => $manquants === [],
        ]);
    }
}

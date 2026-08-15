<?php

namespace App\Livewire\Provider;

use App\Services\Onboarding\ProviderDossierSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * « VOTRE COMPTE N'EST PAS ENCORE VALIDÉ, ET VOICI CE QU'IL RESTE À FAIRE. »
 *
 * LE DÉFAUT QUE CE BANDEAU FERME, relevé en s'inscrivant vraiment : un prestataire crée son
 * compte, arrive sur ce tableau de bord, et y lit « Passez en ligne pour recevoir des missions ».
 * C'est une promesse intenable — son profil est `unverified`, et la requête candidate du dispatch
 * exige `verified`. Il ne recevra JAMAIS rien, quoi qu'il fasse.
 *
 * Rien ne le lui disait. Pas de bandeau, pas d'étape suivante, pas un mot sur le dossier.
 * L'assistant existe pourtant, il fonctionne, et il dit exactement ce qu'il faut — mais il n'était
 * atteignable qu'en parcourant l'annuaire des modules, sous « Mon dossier », en catégorie
 * Conformité, non mis en avant. Le seul lien direct dans les vues se trouvait dans une barre
 * latérale d'ADMINISTRATION.
 *
 * Sur le chemin du recrutement, c'est le plus coûteux des écarts : le prestataire s'inscrit,
 * attend, et s'en va — sans que personne n'apprenne pourquoi.
 *
 * IL NE S'AFFICHE QUE S'IL A QUELQUE CHOSE À DIRE, comme son voisin {@see ProviderDrivingBanner} :
 * un bandeau permanent sur un dossier complet devient du décor, et le jour où il compte vraiment,
 * plus personne ne le lit.
 *
 * IL DISTINGUE DEUX SITUATIONS QUI N'APPELLENT PAS LE MÊME GESTE :
 *  - il reste des choses à faire → on les NOMME, et on renvoie à l'assistant ;
 *  - tout est déposé, l'administration doit relire → on le dit, et on ne réclame rien.
 *
 * Confondre les deux ferait chercher au prestataire une pièce qu'il a déjà fournie.
 */
class ProviderDossierBanner extends Component
{
    /** Au-delà, la liste devient un mur de texte qu'on ne lit plus. L'assistant a le détail. */
    private const MANQUANTS_AFFICHES = 3;

    public function render(): View
    {
        $user = Auth::user();

        $verifie = $user?->providerProfile?->verification_status === 'verified';

        /*
         * UN COMPTE VÉRIFIÉ NE VOIT RIEN. C'est la porte que le dispatch regarde : une fois
         * franchie, ce bandeau n'a plus rien à apprendre à personne.
         */
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

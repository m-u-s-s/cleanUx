<?php

namespace App\Livewire\Admin;

use App\Models\AsapDispatchRequest;
use App\Services\Admin\DemandForecastService;
use App\Services\Admin\FailedSearchRecoveryService;
use App\Services\Admin\MarketplaceHealthService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LA SANTÉ DU MARCHÉ (E30), LA PRÉVISION (E29) ET LE RATTRAPAGE DES ÉCHECS (E31).
 *
 * TROIS MODULES SUR UN ÉCRAN PARCE QU'ILS FORMENT UNE SEULE BOUCLE : on constate qu'une zone
 * décroche, on projette ce qu'il faudra y servir, et on rattrape un par un les clients qu'on a
 * déjà perdus. Répartis sur trois écrans, personne ne ferait le lien entre les trois — et c'est
 * précisément le lien qui déclenche un recrutement.
 *
 * LE TAUX DE RECHERCHE SANS CANDIDAT EST LE SEUL CHIFFRE QUI COMMANDE UNE ACTION. Le reste décrit.
 * Il est donc en tête, et les zones sont triées dessus.
 *
 * ON NE RELANCE PAS UNE RECHERCHE ENCORE OUVERTE : le moteur en ouvrirait une seconde sur la même
 * réservation, et deux prestataires se déplaceraient.
 */
class MarketplaceHealthCenter extends Component
{
    use EnforcesAdminAccess;

    public int $jours = 30;

    public string $messageAuClient = '';

    public int $pourcentageDuGeste = 15;

    #[Locked]
    public ?string $refus = null;

    #[Locked]
    public ?string $confirmation = null;

    public function relancer(int $rechercheId): void
    {
        $recherche = AsapDispatchRequest::query()->find($rechercheId);

        if ($recherche === null) {
            return;
        }

        try {
            app(FailedSearchRecoveryService::class)->relancer($recherche, Auth::user());
            $this->confirmation = 'Recherche relancée.';
            $this->refus = null;
        } catch (DomainException $e) {
            // « Cette recherche n'est pas épuisée » est une règle à LIRE, pas une panne.
            $this->refus = $e->getMessage();
        }
    }

    public function contacter(int $rechercheId): void
    {
        $recherche = AsapDispatchRequest::query()->find($rechercheId);

        if ($recherche === null || trim($this->messageAuClient) === '') {
            $this->refus = 'Écrivez le message à envoyer.';

            return;
        }

        try {
            app(FailedSearchRecoveryService::class)
                ->contacterLeClient($recherche, Auth::user(), $this->messageAuClient);

            $this->reset(['messageAuClient', 'refus']);
            $this->confirmation = 'Message envoyé.';
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function offrirUnGeste(int $rechercheId): void
    {
        $recherche = AsapDispatchRequest::query()->find($rechercheId);

        if ($recherche === null) {
            return;
        }

        try {
            $code = app(FailedSearchRecoveryService::class)
                ->offrirUnGeste($recherche, Auth::user(), $this->pourcentageDuGeste);

            $this->confirmation = 'Code émis : '.$code->code;
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function render(): View
    {
        // Bornée : une fenêtre venue du navigateur ne doit pas faire scanner deux ans de recherches.
        $jours = max(7, min(180, $this->jours));

        $sante = app(MarketplaceHealthService::class);

        return view('livewire.admin.marketplace-health-center', [
            'resume' => $sante->resume(Carbon::now()->subDays($jours), Carbon::now()),
            'zones' => $sante->parZone(Carbon::now()->subDays($jours), Carbon::now()),
            'echecs' => $sante->recherchesEchouees(Carbon::now()->subDays($jours)),
            // Le diagnostic distingue « personne n'était là » de « tout le monde a refusé » : les
            // deux appellent des actions opposées.
            'diagnostics' => $sante->recherchesEchouees(Carbon::now()->subDays($jours))
                ->mapWithKeys(fn (AsapDispatchRequest $r) => [$r->id => $sante->diagnostiquer($r)])
                ->all(),
            'projection' => app(DemandForecastService::class)->projection(),
            'jours' => $jours,
        ])->layout(
            /*
             * `layouts.app` ET NON `layouts.admin` : ce dernier n'existe pas. L'administration
             * partage le gabarit applicatif, et les deux écrans voisins — sécurité, catalogue —
             * le laissent même par défaut. Un gabarit inventé rend 500 à l'ouverture.
             */
            'layouts.app',
        );
    }
}

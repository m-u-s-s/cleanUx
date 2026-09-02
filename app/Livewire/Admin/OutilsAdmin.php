<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * LE CENTRE D'OUTILS, ET CE QU'IL NE DOIT PAS ETRE.
 *
 * La page imbriquait `ProductEmailsCenter` — une page d'administration ROUTEE, avec son propre
 * bandeau editorial — a l'interieur d'une carte : un ecran entier rendu deux fois, une fois chez
 * lui et une fois ici. Un lien suffit, et la page cesse de se dupliquer.
 *
 * `Route::has()` DIT QUE LA PORTE EXISTE, PAS QU'ON A LA CLE : chaque lien est filtre sur la
 * capacite que son module declare, faute de quoi l'administrateur cliquait vers un 403 nu.
 *
 * @property-read array<string, int> $reperes
 * @property-read list<array<string, string>> $pagesLiees
 */
class OutilsAdmin extends Component
{
    use EnforcesAdminAccess;

    /**
     * LES PAGES VOISINES, avec la capacite qui les garde.
     *
     * @var list<array{route: string, titre: string, resume: string}>
     */
    private const VOISINES = [
        ['route' => 'admin.emails', 'titre' => 'E-mails produit',
            'resume' => 'Prévisualiser les e-mails transactionnels et marketing.'],
        ['route' => 'admin.customer.credits', 'titre' => 'Crédits clients',
            'resume' => 'Consulter et ajuster les avoirs accordés aux clients.'],
        ['route' => 'admin.audit.logs', 'titre' => 'Journal d’audit',
            'resume' => 'Retrouver une action sensible et son auteur.'],
        ['route' => 'admin.export.csv', 'titre' => 'Export CSV',
            'resume' => 'Sortie tabulaire complète, hors de cette page.'],
        ['route' => 'admin.export.pdf', 'titre' => 'Export PDF',
            'resume' => 'Document imprimable pour un envoi externe.'],
        ['route' => 'admin.platform.readiness', 'titre' => 'Préparation production',
            'resume' => 'État de la plateforme avant une mise en ligne.'],
    ];

    /**
     * LA CAPACITE EN PLUS DU ROLE.
     *
     * Le module declare `manage-platform` et `module_gate` la pose sur la route — mais
     * `/livewire/update` ne rejoue aucun middleware. Sans cette garde, tout administrateur
     * atteignait les exports et les compteurs par un simple appel de composant.
     */
    public function boot(): void
    {
        Gate::authorize('manage-platform');
    }

    /**
     * L'ETAT REEL DE LA PLATEFORME, en six nombres.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function reperes(): array
    {
        return [
            'utilisateurs' => User::query()->count(),
            'clients' => User::query()->clientFacing()->count(),
            'prestataires' => User::query()->providers()->count(),
            'rendez_vous' => Booking::query()->count(),
            'retours' => Feedback::query()->count(),
            'journaux' => ActivityLog::query()->count(),
        ];
    }

    /**
     * Les pages voisines REELLEMENT ouvrables par ce compte.
     *
     * @return list<array<string, string>>
     */
    #[Computed]
    public function pagesLiees(): array
    {
        $capacites = $this->capacitesParRoute();
        $ouvrables = [];

        foreach (self::VOISINES as $voisine) {
            if (! Route::has($voisine['route'])) {
                continue;
            }

            $capacite = $capacites[$voisine['route']] ?? null;

            // La visibilite se decide sur le MEME test que le middleware qui garde la route.
            if ($capacite !== null && ! Gate::allows($capacite)) {
                continue;
            }

            $ouvrables[] = $voisine + ['url' => route($voisine['route'])];
        }

        return $ouvrables;
    }

    /**
     * La capacite declaree par chaque module, indexee par nom de route.
     *
     * @return array<string, string>
     */
    private function capacitesParRoute(): array
    {
        $table = [];

        foreach ((array) config('modules.catalogue', []) as $module) {
            $route = (string) ($module['route'] ?? '');
            $gate = $module['gate'] ?? null;

            if ($route !== '' && is_string($gate) && $gate !== '') {
                $table[$route] = $gate;
            }
        }

        return $table;
    }

    public function render()
    {
        return view('livewire.admin.outils-admin');
    }
}

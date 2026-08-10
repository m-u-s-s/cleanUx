<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\RecurringBookingSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LES ENTITÉS CENTRALES NE DOIVENT PAS ÊTRE REMPLISSABLES DEPUIS UNE REQUÊTE.
 *
 * CE FICHIER PROTÉGEAIT LE MAUVAIS MODÈLE. Il visait `RendezVous`, qui portait une liste noire
 * refusant `status`, `client_id`, `payment_status` et les montants — et son propre commentaire
 * admettait que « rien n'assigne en masse RendezVous aujourd'hui ». C'était exact : `RendezVous`
 * désignait la table miroir `rendez_vous`, aujourd'hui supprimée. La défense en profondeur était
 * posée sur un modèle que personne n'appelait, pendant que `Booking` — celui que traversent tous
 * les contrôleurs — laisse ces mêmes colonnes assignables parmi ses 128 champs.
 *
 * POURQUOI NE PAS SIMPLEMENT RESTREINDRE `Booking::$fillable` : `status` seul apparaît dans plus de
 * 2700 tableaux littéraux du dépôt, et Eloquent ÉCARTE EN SILENCE un attribut non remplissable au
 * lieu de refuser. Retirer ces colonnes ne casserait pas la suite : elle passerait au vert avec des
 * réservations dont le statut ne serait plus écrit. Le remède serait pire que le mal.
 *
 * CE QUI REND LE TROU EXPLOITABLE, c'est qu'un tableau de requête atteigne l'assignation en masse.
 * Tant qu'aucun appelant ne passe `$request->all()`, la largeur de `$fillable` reste théorique.
 * C'est donc cela que ce fichier surveille — la porte, pas la taille de la pièce.
 */
class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les formes qui font entrer une requête entière dans une écriture de modèle. Chacune a déjà
     * servi de vecteur d'élévation de privilège dans des applications Laravel réelles : il suffit
     * d'ajouter `status=termine` ou `employe_id=<soi>` au formulaire.
     */
    private const FORMES_INTERDITES = [
        '/->fill\(\s*\$request->all\(\)/',
        '/->fill\(\s*request\(\)->all\(\)/',
        '/->update\(\s*\$request->all\(\)/',
        '/->update\(\s*request\(\)->all\(\)/',
        '/::create\(\s*\$request->all\(\)/',
        '/::create\(\s*request\(\)->all\(\)/',
        '/->fill\(\s*\$this->all\(\)/',
    ];

    public function test_aucune_requete_entiere_n_atteint_une_assignation_en_masse(): void
    {
        $coupables = [];

        foreach (['app/Http', 'app/Livewire'] as $dossier) {
            $racine = base_path($dossier);

            if (! is_dir($racine)) {
                continue;
            }

            $fichiers = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            foreach ($fichiers as $fichier) {
                if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($fichier->getPathname());

                foreach (self::FORMES_INTERDITES as $forme) {
                    if (preg_match($forme, $source)) {
                        $coupables[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier->getPathname());
                        break;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Une requête entière alimente une assignation en masse. Sur `Booking`, cela ouvre `status`, ".
            "`client_id`, `employe_id` et `payment_status` au premier champ ajouté au formulaire. ".
            'Passez un tableau explicite, ou les seules clés validées.',
        );
    }

    public function test_recurring_series_allows_its_columns_but_not_id(): void
    {
        $series = (new RecurringBookingSeries)->fill([
            'id' => 999,
            'frequency' => 'weekly',
            'customer_user_id' => 5,
            'status' => 'active',
        ]);

        $this->assertNull($series->id, 'id must not be mass-assignable');
        $this->assertSame('weekly', $series->frequency);
        $this->assertSame(5, $series->customer_user_id);
        $this->assertSame('active', $series->status);
    }

    public function test_les_montants_finaux_restent_hors_de_portee(): void
    {
        // Ce que `Booking` protège RÉELLEMENT aujourd'hui, et qu'il faut donc empêcher de perdre :
        // le prix final et l'identifiant sont hors de la liste blanche. Le reste des colonnes
        // sensibles ne l'est pas — c'est la dette que documente l'en-tête de ce fichier.
        $booking = (new Booking)->fill([
            'id' => 999,
            'final_price' => 9999,
        ]);

        $this->assertNull($booking->id, "l'identifiant ne doit pas être assignable en masse");
        $this->assertNull($booking->final_price, 'le prix final ne doit pas être assignable en masse');
    }

    public function test_user_tenant_id_is_not_mass_assignable(): void
    {
        // M7 — dead tenant_id is not mass-assignable (and the column itself is now dropped).
        $user = (new User)->fill(['name' => 'X', 'tenant_id' => 99]);

        $this->assertNull($user->tenant_id);
        $this->assertSame('X', $user->name);
    }

    public function test_users_table_has_no_tenant_id_column(): void
    {
        // M7 column drop — the dead Tenancy-v2 column is gone (audit_events.tenant_id stays).
        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('audit_events', 'tenant_id'));
    }
}

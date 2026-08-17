<?php

namespace Tests\Feature\ContractsV2;

use App\Models\ContractTemplate;
use Database\Seeders\ContractTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEMER SUR UNE BASE QUI PORTE DÉJÀ UNE VERSION ANTÉRIEURE.
 *
 * L'ANGLE MORT QUE CE FICHIER FERME. Les seeders sont vérifiés sur une base VIERGE — en test comme
 * en CI, où le service MySQL est neuf à chaque exécution. Or le cas qui casse est l'autre : une
 * base qui contient déjà la version précédente du contrat, c'est-à-dire staging et production, et
 * elles seules.
 *
 * `contract_templates` porte deux contraintes d'unicité : `(code, version)` et `code` tout court.
 * La seconde rend la première inopérante — deux versions d'un même contrat ne peuvent pas
 * coexister. Un `updateOrCreate` cherchant sur le couple ne trouve donc rien quand la version
 * change, tente une insertion, et MySQL la refuse. Le déploiement échoue au premier `db:seed`.
 *
 * Ce test ne vérifie pas un numéro de version : il vérifie qu'INCRÉMENTER reste possible, quelle
 * que soit la valeur du jour.
 */
class SeederDeContratsSurBaseDejaSemeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_semer_sur_une_version_anterieure_ne_casse_pas(): void
    {
        $this->seed(ContractTemplatesSeeder::class);

        $modele = ContractTemplate::query()->where('code', 'client_tos')->firstOrFail();
        $versionCourante = (string) $modele->version;

        // On simule l'état d'une base déployée : la version précédente y est déjà.
        $modele->forceFill(['version' => '2026-01-v0'])->save();

        $this->seed(ContractTemplatesSeeder::class);

        $relu = ContractTemplate::query()->where('code', 'client_tos')->firstOrFail();

        $this->assertSame($versionCourante, (string) $relu->version, 'La version doit être remontée.');
        $this->assertSame(
            1,
            ContractTemplate::query()->where('code', 'client_tos')->count(),
            'Une seule ligne par code : `code` est unique, une seconde insertion serait refusée par MySQL.',
        );
    }

    /**
     * LA RÈGLE DE FACTURATION AU TEMPS DOIT ÊTRE DANS LES DEUX CONTRATS.
     *
     * Elle engage de l'argent des deux côtés : le client paie une majoration, le prestataire ne la
     * touche pas. Une règle appliquée sans être écrite au contrat n'est pas opposable.
     */
    public function test_la_regle_du_temps_figure_dans_les_deux_contrats(): void
    {
        $this->seed(ContractTemplatesSeeder::class);

        $client = ContractTemplate::query()->where('code', 'client_tos')->firstOrFail();
        $prestataire = ContractTemplate::query()->where('code', 'provider_agreement')->firstOrFail();

        $this->assertStringContainsString('temps passé', (string) $client->body_markdown);
        $this->assertStringContainsString('prolonger', (string) $client->body_markdown);

        $this->assertStringContainsString('temps passé', (string) $prestataire->body_markdown);
        $this->assertStringContainsString(
            'tarif horaire NORMAL',
            (string) $prestataire->body_markdown,
            'Le prestataire doit lire noir sur blanc qu’il ne touche pas la majoration.',
        );
    }

    /** Le seeder reste idempotent : la CI le lance deux fois de suite. */
    public function test_le_seeder_reste_idempotent(): void
    {
        $this->seed(ContractTemplatesSeeder::class);
        $apresUn = ContractTemplate::query()->count();

        $this->seed(ContractTemplatesSeeder::class);

        $this->assertSame($apresUn, ContractTemplate::query()->count());
        // Témoin : le seeder produit bien quelque chose. Sans cela, « rien n'a changé » serait vrai
        // pour un seeder qui n'écrit rien du tout.
        $this->assertGreaterThan(0, $apresUn);
    }
}

<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\Field;
use App\Admin\Console\ResourceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Le garde-fou générique des descripteurs — il vaut pour TOUS, y compris ceux à venir. */
class DescriptorSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Domaines dont la création ne peut pas être devinée, et pourquoi.
     *
     * @var array<string, string>
     */
    private const EXEMPTS = [
        // La création d'un compte exige un email unique ET un mot de passe : les valeurs
        // plausibles suffisent, mais l'unicité rend l'essai répété instable dans une même classe.
        'users' => 'unicité de l’email entre deux essais du même test',
    ];

    /**
     * Les domaines servis par le moteur.
     *
     * @return array<string, array{string}>
     */
    public static function domainesAvecFormulaire(): array
    {
        /** @var array{modules: list<array{key: string, coverage: string}>} $registre */
        $registre = require dirname(__DIR__, 4).'/config/admin_console.php';

        $cas = [];

        foreach ($registre['modules'] as $module) {
            if ($module['coverage'] === 'descriptor') {
                $cas[$module['key']] = [$module['key']];
            }
        }

        // Un fournisseur vide ferait passer ce fichier pour vert sans rien éprouver.
        if ($cas === []) {
            $cas['aucun descripteur enregistré'] = ['__aucun__'];
        }

        return $cas;
    }

    #[DataProvider('domainesAvecFormulaire')]
    public function test_chaque_option_declaree_est_acceptee_par_la_base(string $resource): void
    {
        $descripteur = app(ResourceRegistry::class)->for($resource);
        $this->assertNotNull($descripteur, "Descripteur introuvable pour « {$resource} ».");

        $champs = $descripteur->formFields();

        if ($champs === []) {
            // Domaine en lecture seule : rien à créer, rien à éprouver ici.
            $this->addToAssertionCount(1);

            return;
        }

        if (isset(self::EXEMPTS[$resource])) {
            $this->markTestSkipped("« {$resource} » exempté : ".self::EXEMPTS[$resource]);
        }

        Sanctum::actingAs(User::factory()->adminComplet()->create(), ['*']);

        $selects = array_values(array_filter(
            $champs,
            fn (Field $f) => $f->toArray()['type'] === 'select' && $f->toArray()['options'] !== [],
        ));

        // Un domaine sans liste de choix n'a pas ce risque : on vérifie quand même qu'une
        // création nominale ne tombe pas en 500.
        $combinaisons = $selects === []
            ? [[]]
            : $this->combinaisonsDeSelects($selects);

        // TOUTES LES OPTIONS QUE LA BASE REFUSE, PAS LA PREMIÈRE.
        $refusees = [];

        foreach ($combinaisons as $index => $forcees) {
            $charge = $this->chargePlausible($champs, $forcees, $index);
            $reponse = $this->postJson("/api/admin/console/{$resource}", $charge);

            if ($reponse->status() === 500) {
                $refusees[] = json_encode($forcees, JSON_UNESCAPED_UNICODE);
            }
        }

        $this->assertSame([], $refusees, "Ces options annoncées par « {$resource} » sont refusées par la base.");
    }

    /**
     * Une combinaison par option de chaque liste de choix — pas le produit cartésien, qui exploserait sans rien prouver de plus : le risque est par OPTION, pas par croisement.
     *
     * @param  list<Field>  $selects
     * @return list<array<string, string>>
     */
    private function combinaisonsDeSelects(array $selects): array
    {
        $combinaisons = [];

        $maximum = 0;
        foreach ($selects as $select) {
            $maximum = max($maximum, count($select->toArray()['options']));
        }

        for ($rang = 0; $rang < $maximum; $rang++) {
            $courante = [];

            foreach ($selects as $select) {
                $options = $select->toArray()['options'];
                // La dernière option est réutilisée quand une liste est plus courte qu'une autre :
                // chaque option de chaque liste est ainsi essayée au moins une fois.
                $option = $options[min($rang, count($options) - 1)];
                $courante[$select->key()] = $option['value'];
            }

            $combinaisons[] = $courante;
        }

        return $combinaisons;
    }

    /**
     * Des valeurs plausibles par type, uniques par essai.
     *
     * @param  list<Field>  $champs
     * @param  array<string, string>  $forcees
     * @return array<string, mixed>
     */
    private function chargePlausible(array $champs, array $forcees, int $essai): array
    {
        $charge = [];

        foreach ($champs as $champ) {
            $forme = $champ->toArray();
            $cle = $forme['key'];

            if (isset($forcees[$cle])) {
                $charge[$cle] = $forcees[$cle];

                continue;
            }

            $charge[$cle] = match ($forme['type']) {
                'email' => "essai{$essai}@example.test",
                'phone' => '+3247000000'.($essai % 10),
                'number', 'money' => 1,
                'date' => now()->addDays($essai)->toDateString(),
                'bool' => true,
                'select' => $forme['options'][0]['value'] ?? 'x',
                // Assez long pour satisfaire les règles `min:` que portent les motifs écrits.
                default => "Essai automatique numéro {$essai}",
            };
        }

        return $charge;
    }
}

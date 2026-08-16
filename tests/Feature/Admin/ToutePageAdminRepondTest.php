<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TOUTE PAGE D'ADMINISTRATION RÉPOND — ou on sait laquelle ne répond pas.
 *
 * Quatre-vingt-treize écrans sans paramètre, et aucun ne était ouvert en bloc. Une page qui lève
 * une erreur 500 sur une requête vide — une relation absente, une colonne renommée, une propriété
 * magique que PHPStan ne voit pas — ne se découvre qu'au moment où quelqu'un clique. Les tests
 * unitaires de chaque centre couvrent leur logique, pas le fait que la vue se rende.
 *
 * LE GARDE-FOU DE LA MESURE : si l'énumération cesse de trouver des routes, ce test passerait au
 * vert en ne vérifiant plus rien. La borne basse rend ce silence impossible.
 */
class ToutePageAdminRepondTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les pages écartées, et pourquoi. Une exclusion silencieuse ferait passer un écran cassé
     * pour un écran couvert.
     *
     * @var list<string>
     */
    private const HORS_PERIMETRE = [
        // Téléchargements et exports : ils rendent un fichier, pas une page.
        'admin/quality/export/incidents.csv',
    ];

    public function test_chaque_page_admin_sans_parametre_repond(): void
    {
        /*
         * UN SUPER-ADMINISTRATEUR, ET C'EST DELIBERE.
         *
         * Quatre ecrans portent une permission granulaire en plus du role — `manage-modules`,
         * `manage-services`, `manage-entreprises`, `perform-critical-admin-actions`. Un
         * administrateur ordinaire qui ne les a pas recoit un 403 LEGITIME : ce balayage
         * mesurerait alors le controle d'acces au lieu de mesurer si la page se rend.
         *
         * Que ces memes ecrans ne soient pas PROPOSES a qui n'y a pas droit est une autre
         * question, testee separement dans `NavAdminNOffrePasDePorteFermeeTest`.
         */
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'status' => 'active',
            'platform_role' => 'super_admin',
        ]);

        $this->actingAs($admin);

        $uris = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, 'admin/'))
            // Les routes à paramètre demandent un enregistrement existant : elles relèvent des
            // tests de leur propre écran, pas d'un balayage.
            ->reject(fn (string $uri) => str_contains($uri, '{'))
            ->reject(fn (string $uri) => in_array($uri, self::HORS_PERIMETRE, true))
            ->unique()
            ->values();

        $this->assertGreaterThan(
            50,
            $uris->count(),
            'Le balayage ne trouve presque aucune page : il a cessé de mesurer.',
        );

        $casses = [];

        foreach ($uris as $uri) {
            try {
                $reponse = $this->get('/'.$uri);
                $code = $reponse->getStatusCode();

                // 200 attendu ; 302 accepté (une redirection est une décision, pas une panne).
                if ($code >= 400) {
                    $casses[] = sprintf('%s → HTTP %d', $uri, $code);
                }
            } catch (\Throwable $e) {
                $casses[] = sprintf('%s → %s : %s', $uri, class_basename($e), $e->getMessage());
            }
        }

        sort($casses);

        $this->assertSame([], $casses, sprintf(
            "%d page(s) d'administration ne répondent pas :\n%s",
            count($casses),
            implode("\n", array_map(static fn (string $l): string => '  - '.$l, $casses)),
        ));
    }
}

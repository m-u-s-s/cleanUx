<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** TOUTE PAGE D'ADMINISTRATION RÉPOND — ou on sait laquelle ne répond pas. */
class ToutePageAdminRepondTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les pages écartées, et pourquoi.
     *
     * @var list<string>
     */
    private const HORS_PERIMETRE = [
        // Téléchargements et exports : ils rendent un fichier, pas une page.
        'admin/quality/export/incidents.csv',
    ];

    public function test_chaque_page_admin_sans_parametre_repond(): void
    {
        // UN SUPER-ADMINISTRATEUR, ET C'EST DELIBERE.
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

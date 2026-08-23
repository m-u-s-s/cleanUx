<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** L'annuaire que voit l'administrateur sur mobile. */
class AdminCatalogEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
    }

    public function test_l_annuaire_rend_les_groupes_dans_l_ordre_du_registre(): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson('/api/admin/catalog')->assertOk();

        $res->assertJsonPath('ok', true);
        $this->assertSame(
            array_keys(config('admin_console.groups')),
            array_column($res->json('groups'), 'key'),
        );
    }

    public function test_l_annuaire_n_oublie_aucun_module(): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson('/api/admin/catalog')->assertOk();

        $servis = collect($res->json('groups'))->flatMap(fn (array $g) => $g['modules']);

        // Le contrat de l'annuaire, c'est l'exhaustivité : un module perdu en route serait une
        // page d'administration devenue inatteignable sans que rien ne le signale.
        $attendues = array_column(config('admin_console.modules'), 'key');
        sort($attendues);

        $servies = $servis->pluck('key')->all();
        sort($servies);

        $this->assertSame($attendues, $servies);
    }

    public function test_les_compteurs_disent_ce_qui_reste_a_couvrir(): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson('/api/admin/catalog')->assertOk();

        $this->assertSame(count(config('admin_console.modules')), $res->json('counts.total'));
        $this->assertSame(
            $res->json('counts.total'),
            $res->json('counts.covered') + $res->json('counts.pending'),
        );
    }

    public function test_chaque_module_porte_ce_qu_il_faut_pour_l_afficher(): void
    {
        $this->actingAsAdmin();

        $modules = collect($this->getJson('/api/admin/catalog')->json('groups'))
            ->flatMap(fn (array $g) => $g['modules']);

        // Un catalogue mal forme l'est rarement sur une seule entree : on les nomme toutes.
        $fautifs = [];

        foreach ($modules as $i => $module) {
            $nom = $module['key'] ?? "module #{$i}";

            foreach (['key', 'title', 'icon', 'route'] as $clef) {
                if (blank($module[$clef] ?? null)) {
                    $fautifs[] = "{$nom} : « {$clef} » vide";
                }
            }

            if (! in_array($module['coverage'] ?? null, ['pending', 'descriptor', 'report', 'screen'], true)) {
                $fautifs[] = "{$nom} : couverture « ".($module['coverage'] ?? 'null').' » inconnue';
            }
        }

        $this->assertSame([], $fautifs, 'Ces modules du catalogue sont inexploitables par le mobile.');
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson('/api/admin/catalog')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }
}

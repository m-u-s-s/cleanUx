<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\ReportRegistry;
use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les rapports d'administration — les modules qui ne sont pas des listes.
 *
 * Mêmes garde-fous que pour les descripteurs, dans les deux sens : un module annoncé `report`
 * sans rapport enregistré ouvrirait un écran vide ; un rapport écrit mais laissé « à venir »
 * serait du travail livré que personne ne voit.
 *
 * ET UN DE PLUS, propre aux rapports : chaque tuile doit être MESURABLE. Le contrat rattrape les
 * erreurs pour qu'une table absente coûte une tuile plutôt que l'écran ; sans ce test, une
 * requête cassée rendrait zéro et tout aurait l'air normal.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function rapports(): array
    {
        /** @var array{modules: list<array{key: string, coverage: string}>} $registre */
        $registre = require dirname(__DIR__, 4).'/config/admin_console.php';

        $cas = [];

        foreach ($registre['modules'] as $module) {
            if ($module['coverage'] === 'report') {
                $cas[$module['key']] = [$module['key']];
            }
        }

        return $cas === [] ? ['aucun rapport' => ['__aucun__']] : $cas;
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->adminComplet()->create(), ['*']);
    }

    public function test_tout_module_declare_report_a_bien_un_rapport(): void
    {
        $registre = app(ReportRegistry::class);

        $manquants = [];

        foreach (config('admin_console.modules') as $module) {
            if ($module['coverage'] === 'report' && ! $registre->has($module['key'])) {
                $manquants[] = $module['key'];
            }
        }

        $this->assertSame([], $manquants,
            'Modules annoncés en synthèse sans rapport enregistré : '.implode(', ', $manquants));
    }

    public function test_tout_rapport_enregistre_est_annonce_comme_tel(): void
    {
        $couverture = collect(config('admin_console.modules'))->pluck('coverage', 'key');

        $tus = array_values(array_filter(
            app(ReportRegistry::class)->keys(),
            fn (string $key) => $couverture[$key] !== 'report',
        ));

        $this->assertSame([], $tus,
            'Rapports livrés mais encore annoncés autrement : '.implode(', ', $tus));
    }

    #[DataProvider('rapports')]
    public function test_chaque_tuile_est_mesurable(string $report): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson("/api/admin/console/reports/{$report}")->assertOk();

        $sections = $res->json('sections');
        $this->assertNotEmpty($sections, "Le rapport « {$report} » n’a aucune section.");

        $tuiles = collect($sections)->flatMap(fn (array $s) => $s['tiles']);
        $this->assertNotEmpty($tuiles, "Le rapport « {$report} » n’a aucune tuile.");

        foreach ($tuiles as $tuile) {
            $this->assertNotEmpty($tuile['key']);
            $this->assertNotEmpty($tuile['label']);
            $this->assertContains($tuile['tone'], ['neutral', 'success', 'warning', 'danger']);

            // Le contrat rattrape les erreurs pour qu'une table absente coûte une tuile et non
            // l'écran. Sans cette assertion, une requête cassée rendrait zéro et le test passerait
            // — le vert qui ne prouve rien.
            $this->assertTrue($tuile['available'], sprintf(
                'La tuile « %s » du rapport « %s » n’a pas pu être mesurée.',
                $tuile['key'],
                $report,
            ));
        }
    }

    #[DataProvider('rapports')]
    public function test_les_cles_de_tuile_sont_uniques_dans_un_rapport(string $report): void
    {
        $this->actingAsAdmin();

        $cles = collect($this->getJson("/api/admin/console/reports/{$report}")->json('sections'))
            ->flatMap(fn (array $s) => array_column($s['tiles'], 'key'))
            ->all();

        // Deux tuiles de même clé se remplaceraient l'une l'autre côté mobile, qui les rend par
        // clé : l'une des deux mesures disparaîtrait sans rien signaler.
        $this->assertSame(array_values(array_unique($cles)), $cles);
    }

    public function test_un_rapport_inconnu_rend_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/console/reports/licornes')
            ->assertStatus(404)
            ->assertJsonPath('error', 'unknown_report');
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson('/api/admin/console/reports/home')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }

    public function test_une_file_non_vide_est_annoncee_comme_telle(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create(['status' => BookingStatus::EN_ATTENTE]);

        $tuiles = collect($this->getJson('/api/admin/console/reports/home')->json('sections'))
            ->flatMap(fn (array $s) => $s['tiles'])
            ->keyBy('key');

        // Une file d'attente non vide est une charge de travail : l'annoncer est tout l'intérêt
        // de cet écran, et un ton neutre la ferait passer inaperçue.
        $this->assertSame(1, $tuiles['bookings_pending']['value']);
        $this->assertSame('warning', $tuiles['bookings_pending']['tone']);
    }
}

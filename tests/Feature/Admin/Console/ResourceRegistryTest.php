<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\ResourceRegistry;
use Tests\TestCase;

/** Le registre des descripteurs, et son garde-fou contre le mensonge. */
class ResourceRegistryTest extends TestCase
{
    public function test_tout_module_declare_descriptor_a_bien_un_descripteur(): void
    {
        $registre = app(ResourceRegistry::class);

        $manquants = [];

        foreach (config('admin_console.modules') as $module) {
            if ($module['coverage'] === 'descriptor' && ! $registre->has($module['key'])) {
                $manquants[] = $module['key'];
            }
        }

        $this->assertSame([], $manquants,
            'Modules annoncés disponibles sans descripteur enregistré : '.implode(', ', $manquants));
    }

    public function test_tout_descripteur_enregistre_correspond_a_un_module_connu(): void
    {
        // Les clés d'annuaire ET les ressources SECONDAIRES qu'un module déclare.
        $connus = array_merge(
            array_column(config('admin_console.modules'), 'key'),
            collect(config('admin_console.modules'))->flatMap(fn ($m) => $m['resources'] ?? [])->all(),
        );

        // Une LISTE de violations plutôt qu'une assertion par tour de boucle : sur un registre
        // encore vide, une boucle assertive ne produirait aucune assertion et PHPUnit marquerait
        // le test « risky » — un vert qui ne prouve rien, exactement ce qu'on cherche à éviter.
        $orphelins = array_values(array_diff(app(ResourceRegistry::class)->keys(), $connus));

        $this->assertSame([], $orphelins,
            'Descripteurs qu’aucune entrée d’annuaire n’ouvre : '.implode(', ', $orphelins));
    }

    public function test_tout_descripteur_enregistre_est_annonce_comme_tel(): void
    {
        $couverture = collect(config('admin_console.modules'))->pluck('coverage', 'key');

        // L'inverse du premier test : un descripteur écrit mais laissé « à venir » dans le
        // registre reste invisible dans l'annuaire. Du travail livré que personne ne voit.
        $secondaires = collect(config('admin_console.modules'))
            ->flatMap(fn ($m) => $m['resources'] ?? [])
            ->all();

        $tus = array_values(array_filter(
            app(ResourceRegistry::class)->keys(),
            // Une ressource secondaire n'a pas d'entrée propre dans l'annuaire : c'est son module
            // qui l'annonce, et c'est la couverture de CELUI-CI qui compte.
            fn (string $key) => ! in_array($key, $secondaires, true)
                && ($couverture[$key] ?? null) !== 'descriptor',
        ));

        $this->assertSame([], $tus,
            'Descripteurs livrés mais encore annoncés « à venir » : '.implode(', ', $tus));
    }

    public function test_un_module_inconnu_ne_rend_rien(): void
    {
        $this->assertNull(app(ResourceRegistry::class)->for('module-qui-n-existe-pas'));
        $this->assertFalse(app(ResourceRegistry::class)->has('module-qui-n-existe-pas'));
    }

    public function test_le_registre_est_partage(): void
    {
        // Résolu en singleton : chaque instanciation reconstruirait tous les descripteurs, et une
        // liste d'annuaire en ferait autant de fois qu'elle a de lignes.
        $this->assertSame(app(ResourceRegistry::class), app(ResourceRegistry::class));
    }
}

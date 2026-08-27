<?php

namespace Tests\Feature\Security;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Livewire\AdminDashboard;
use App\Livewire\AdminFeedbacks;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/** Defense-in-depth — chaque composant Livewire admin doit refuser (403) un non-admin au niveau composant (boot), pas seulement via la middleware de route. */
class AdminComponentGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function adminComponents(): array
    {
        return [
            'AdminDashboard' => [AdminDashboard::class],
            'AdminFeedbacks' => [AdminFeedbacks::class],
            // Route sur /admin/utilisateurs depuis le 2026-08-27 : il change des roles.
            'GestionUtilisateurs' => [GestionUtilisateurs::class],
        ];
    }

    /**
     * @dataProvider adminComponents
     */
    public function test_component_blocks_non_admin(string $component): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test($component)->assertForbidden();
    }

    /**
     * @dataProvider adminComponents
     */
    public function test_component_uses_enforces_admin_access(string $component): void
    {
        $this->assertContains(
            EnforcesAdminAccess::class,
            class_uses_recursive($component),
            "$component doit utiliser EnforcesAdminAccess."
        );
    }

    /** Garde de complétude — tout composant Livewire du namespace App\Livewire\Admin doit être gardé. */
    public function test_all_admin_namespace_components_are_guarded(): void
    {
        $dir = app_path('Livewire/Admin');
        $missing = [];

        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = 'App\\Livewire\\Admin\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Component::class)) {
                continue;
            }
            if (! in_array(EnforcesAdminAccess::class, class_uses_recursive($class), true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'Composants admin non gardés : '.implode(', ', $missing));
    }
}

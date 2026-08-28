<?php

namespace Tests\Feature\Console;

use App\Console\Commands\LivewireMissingViews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/** Coverage for {@see LivewireMissingViews}. */
class LivewireMissingViewsCoverageBatch18Test extends TestCase
{
    private string $tempDir = '';

    /** @var array<int, string> */
    private array $createdViews = [];

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        foreach ($this->createdViews as $view) {
            if (is_file($view)) {
                @unlink($view);
            }
        }

        parent::tearDown();
    }

    public function test_command_succeeds_against_the_live_codebase(): void
    {
        $this->artisan('livewire:missing-views')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_fails_when_the_livewire_directory_is_absent(): void
    {
        File::partialMock()
            ->shouldReceive('isDirectory')
            ->andReturn(false);

        $this->artisan('livewire:missing-views')
            ->expectsOutputToContain("Le dossier app/Livewire n'existe pas.")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_reports_components_missing_their_blade(): void
    {
        $this->tempDir = sys_get_temp_dir().'/cov_batch18_'.uniqid();
        File::makeDirectory($this->tempDir.'/Sub', 0777, true, true);

        // 1) Declared view that resolves to an existing blade -> skipped (continue).
        $existingBlade = resource_path('views/_cov_batch18_present.blade.php');
        File::put($existingBlade, 'present');
        $this->createdViews[] = $existingBlade;

        $presentFile = $this->tempDir.'/Present.php';
        File::put($presentFile, "<?php return view('_cov_batch18_present');");

        // 2) Declared view that does not resolve -> reported as missing.
        $declaredFile = $this->tempDir.'/Declared.php';
        File::put($declaredFile, "<?php return view('cov_batch18.does.not.exist');");

        // 3) No declared view and the conventional blade is absent -> reported as missing.
        $conventionalFile = $this->tempDir.'/Sub/Conventional.php';
        File::put($conventionalFile, '<?php // no view call here');

        $files = [
            new SplFileInfo($presentFile, '', 'Present.php'),
            new SplFileInfo($declaredFile, '', 'Declared.php'),
            new SplFileInfo($conventionalFile, 'Sub', 'Sub/Conventional.php'),
        ];

        $mock = File::partialMock();
        $mock->shouldReceive('isDirectory')->andReturn(true);
        $mock->shouldReceive('allFiles')->andReturn($files);

        $this->artisan('livewire:missing-views')
            ->expectsOutputToContain('Composants sans blade associé')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * Un trait de `app/Livewire/Concerns` n'est pas un composant : il ne rend aucune vue.
     *
     * TEMOIN : la classe voisine, elle, est bien signalee. Sans elle, ce test resterait vert
     * si la commande cessait de signaler quoi que ce soit.
     */
    public function test_le_code_partage_n_est_pas_pris_pour_un_composant(): void
    {
        $this->tempDir = sys_get_temp_dir().'/cov_batch18_traits_'.uniqid();
        File::makeDirectory($this->tempDir.'/Concerns', 0777, true, true);

        $trait = $this->tempDir.'/Concerns/PartageQuelqueChose.php';
        File::put($trait, "<?php

namespace App\Livewire\Concerns;

trait PartageQuelqueChose
{
}
");

        $abstraite = $this->tempDir.'/BaseAbstraite.php';
        File::put($abstraite, '<?php

abstract class BaseAbstraite
{
}
');

        $composant = $this->tempDir.'/SansVue.php';
        File::put($composant, '<?php class SansVue {}');

        $mock = File::partialMock();
        $mock->shouldReceive('isDirectory')->andReturn(true);
        $mock->shouldReceive('allFiles')->andReturn([
            new SplFileInfo($trait, 'Concerns', 'Concerns/PartageQuelqueChose.php'),
            new SplFileInfo($abstraite, '', 'BaseAbstraite.php'),
            new SplFileInfo($composant, '', 'SansVue.php'),
        ]);

        $this->artisan('livewire:missing-views')
            ->expectsOutputToContain('SansVue')
            ->doesntExpectOutputToContain('PartageQuelqueChose')
            ->doesntExpectOutputToContain('BaseAbstraite')
            ->assertExitCode(Command::FAILURE);
    }
}

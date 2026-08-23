<?php

namespace Tests\Feature\Console;

use App\Console\Commands\GeneratePwaIconsCommand;
use Illuminate\Console\Command;
use Tests\TestCase;

/** Coverage for {@see GeneratePwaIconsCommand}. */
class GeneratePwaIconsCommandCoverageBatch6Test extends TestCase
{
    public function test_fails_cleanly_when_imagemagick_is_missing(): void
    {
        $this->artisan('pwa:icons')
            ->expectsOutputToContain('ImageMagick introuvable')
            ->expectsOutputToContain('apt install imagemagick')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_options_are_parsed_before_the_binary_check(): void
    {
        // Exercises the option reading (source/bg/label/force) and the
        // mb_substr label truncation branch; still falls through to the same
        // missing-binary FAILURE because the dependency is absent.
        $this->artisan('pwa:icons', [
            '--source' => 'nonexistent-logo.png',
            '--bg' => '#10b981',
            '--label' => 'BRIO',
            '--force' => true,
        ])
            ->expectsOutputToContain('ImageMagick introuvable')
            ->assertExitCode(Command::FAILURE);
    }
}

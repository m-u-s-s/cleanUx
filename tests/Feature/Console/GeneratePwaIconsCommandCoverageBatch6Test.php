<?php

namespace Tests\Feature\Console;

use App\Console\Commands\GeneratePwaIconsCommand;
use Illuminate\Console\Command;
use Tests\TestCase;

/**
 * Coverage for {@see GeneratePwaIconsCommand}.
 *
 * The command depends on ImageMagick ('magick' or 'convert' on the PATH). The
 * test environment does not ship ImageMagick, so detectConvert() returns null
 * and handle() takes the early FAILURE guard. These tests exercise the option
 * parsing (source/bg/label/force) plus detectConvert() and the missing-binary
 * error branch deterministically. The actual image-generation branches require
 * ImageMagick and are not reachable here.
 */
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

<?php

namespace Tests\Feature\GoLive;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Pré-vol go-live — orchestre les contrôles automatisables des drills (D1 parité + santé, D5 stuck-missions) et rappelle les drills manuels restants (D2/D3/D4/D6). */
class GoLivePreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_runs_automatable_checks_and_lists_manual_drills(): void
    {
        $this->artisan('golive:preflight')
            ->expectsOutputToContain('config:parity-check')
            ->expectsOutputToContain('spine:check-stuck-missions')
            ->expectsOutputToContain('MANUEL')
            ->expectsOutputToContain('D2');
    }

    public function test_preflight_gate_fails_when_missions_are_stuck(): void
    {
        // En env de test, la parité échoue (sqlite/array) → le pré-vol doit sortir != 0.
        $exit = Artisan::call('golive:preflight');
        $this->assertNotSame(0, $exit, 'Le pré-vol doit bloquer si un gate (parité/stuck) échoue.');
    }
}

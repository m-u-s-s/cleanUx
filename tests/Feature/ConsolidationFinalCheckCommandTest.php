<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsolidationFinalCheckCommandTest extends TestCase
{
    #[Test]
    public function the_command_runs_and_prints_a_summary(): void
    {
        $this->artisan('app:consolidation-final-check')
            ->expectsOutputToContain('Total flags')
            ->expectsOutputToContain('Catégories impactées')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature\Sms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** RELIRE LES CODES SANS TÉLÉPHONE — et refuser de le faire en production. */
class DernierCodeSmsTest extends TestCase
{
    use RefreshDatabase;

    private function sms(string $corps, string $telephone = '+32470000999', string $statut = 'sent'): void
    {
        DB::table('sms_messages')->insert([
            'provider' => 'mock',
            'to_phone' => $telephone,
            'body' => $corps,
            'status' => $statut,
            'category' => 'transactional',
            // `queued_at` est NOT NULL : le registre date l'entrée en file, pas seulement la ligne.
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function elle_montre_le_code_a_six_chiffres(): void
    {
        $this->sms('Brio : votre employé est arrivé. Code de début : 299378');

        $this->artisan('sms:dernier-code')
            ->expectsOutputToContain('299378')
            ->assertSuccessful();
    }

    #[Test]
    public function elle_filtre_sur_le_numero(): void
    {
        $this->sms('Brio : code de début : 111111', '+32470000999');
        $this->sms('Brio : code de début : 222222', '+32471111111');

        // Deux appareils de test sur la même base : lire le code du voisin ferait échouer la
        // vérification sans qu'on comprenne pourquoi.
        $this->artisan('sms:dernier-code', ['telephone' => '+32471111111'])
            ->expectsOutputToContain('222222')
            ->doesntExpectOutputToContain('111111')
            ->assertSuccessful();
    }

    #[Test]
    public function le_statut_est_affiche_car_il_explique_les_absences(): void
    {
        // `rate_limited` veut dire que le plafond par numéro est atteint : en développement le corps
        // est enregistré quand même, mais en production le client n'aurait rien reçu. Le cacher
        // ferait conclure à un code perdu.
        $this->sms('Brio : code de fin de mission : 377454', '+32470000999', 'rate_limited');

        $this->artisan('sms:dernier-code')
            ->expectsOutputToContain('rate_limited')
            ->assertSuccessful();
    }

    #[Test]
    public function un_message_sans_code_est_ecarte_par_defaut(): void
    {
        $this->sms('Brio : votre intervention est confirmée.');

        $this->artisan('sms:dernier-code')
            ->expectsOutputToContain('Aucun code à six chiffres')
            ->assertSuccessful();
    }

    #[Test]
    public function tout_les_montre_quand_meme(): void
    {
        $this->sms('Brio : votre intervention est confirmée.');

        $this->artisan('sms:dernier-code', ['--tout' => true])
            ->expectsOutputToContain('confirmée')
            ->assertSuccessful();
    }

    #[Test]
    public function elle_refuse_de_s_executer_en_production(): void
    {
        $this->sms('Brio : code de début : 299378');

        app()->detectEnvironment(fn () => 'production');

        $this->artisan('sms:dernier-code')
            ->expectsOutputToContain('Refusé en production')
            ->doesntExpectOutputToContain('299378')
            ->assertFailed();
    }
}

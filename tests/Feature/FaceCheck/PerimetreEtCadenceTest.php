<?php

namespace Tests\Feature\FaceCheck;

use App\Models\PlatformModule;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\FaceCheck\FaceCheckRequirement;
use App\Services\FaceCheck\FaceCheckScheduler;
use App\Services\FaceCheck\FaceCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FaceCheck\Concerns\ActiveLeControleFacial;
use Tests\TestCase;

class PerimetreEtCadenceTest extends TestCase
{
    use ActiveLeControleFacial;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();
        $this->activerLeControleFacial();
    }

    // ─────────────────────────────────────────────────────────────
    // Périmètre : les deux notions distinctes
    // ─────────────────────────────────────────────────────────────

    public function test_un_prestataire_dun_metier_soumis_dans_une_zone_couverte_est_soumis(): void
    {
        $prestataire = $this->prestataireSoumis();

        $this->assertTrue(app(FaceCheckRequirement::class)->appliesToProvider($prestataire));
    }

    /** TÉMOIN : hors métier et hors zone, le module n'existe pas pour lui. */
    public function test_un_prestataire_hors_perimetre_nest_pas_soumis(): void
    {
        $prestataire = $this->prestataireHorsPerimetre();

        $this->assertFalse(app(FaceCheckRequirement::class)->appliesToProvider($prestataire));
    }

    public function test_le_bon_metier_dans_une_zone_non_couverte_nest_pas_soumis(): void
    {
        $prestataire = $this->prestataireSoumis();
        $autreZone = ServiceZone::factory()->create();
        $prestataire->forceFill(['primary_service_zone_id' => $autreZone->id])->save();

        $this->assertFalse(app(FaceCheckRequirement::class)->appliesToProvider($prestataire->refresh()));
    }

    public function test_module_eteint_personne_nest_soumis(): void
    {
        $prestataire = $this->prestataireSoumis();
        $this->assertTrue(app(FaceCheckRequirement::class)->appliesToProvider($prestataire));

        $this->eteindreLeControleFacial();

        $this->assertFalse(app(FaceCheckRequirement::class)->appliesToProvider($prestataire));
    }

    /** Les zones du prestataire ne se réduisent pas à sa zone principale : le résolveur de modules, lui, ne lit que celle-là. */
    public function test_une_zone_affectee_compte_autant_que_la_zone_principale(): void
    {
        $prestataire = $this->prestataireSoumis();
        $autreZone = ServiceZone::factory()->create();
        $prestataire->forceFill(['primary_service_zone_id' => $autreZone->id])->save();

        DB::table('employee_zone_assignments')->insert([
            'user_id' => $prestataire->id,
            'service_zone_id' => $this->zoneDuControle->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(FaceCheckRequirement::class)->appliesToProvider($prestataire->refresh()));
    }

    // ─────────────────────────────────────────────────────────────
    // La porte
    // ─────────────────────────────────────────────────────────────

    public function test_un_prestataire_non_enrole_doit_senroler(): void
    {
        $prestataire = $this->prestataireSoumis();

        $verdict = app(FaceCheckGate::class)->inspectProvider($prestataire);

        $this->assertFalse($verdict->allowed());
        $this->assertSame(FaceCheckDecision::ENROLMENT_REQUIRED, $verdict->code);
    }

    /** TÉMOIN POSITIF : hors périmètre, la porte est ouverte. */
    public function test_un_prestataire_hors_perimetre_passe_la_porte(): void
    {
        $prestataire = $this->prestataireHorsPerimetre();

        $this->assertTrue(app(FaceCheckGate::class)->inspectProvider($prestataire)->allowed());
    }

    public function test_un_prestataire_enrole_et_a_jour_passe_la_porte(): void
    {
        $prestataire = $this->prestataireEnrole();

        $this->assertTrue(app(FaceCheckGate::class)->inspectProvider($prestataire)->allowed());
    }

    public function test_un_prestataire_dont_lecheance_est_passee_doit_repasser_un_controle(): void
    {
        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        $verdict = app(FaceCheckGate::class)->inspectProvider($prestataire);

        $this->assertSame(FaceCheckDecision::CHECK_REQUIRED, $verdict->code);
        $this->assertSame(ProviderFaceCheck::TRIGGER_INTERVAL, $verdict->trigger);
    }

    public function test_un_prestataire_bloque_ne_passe_pas(): void
    {
        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);
        app(FaceCheckService::class)->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);

        $verdict = app(FaceCheckGate::class)->inspectProvider($prestataire);

        $this->assertSame(FaceCheckDecision::BLOCKED, $verdict->code);
    }

    public function test_le_retrait_du_consentement_ferme_la_porte(): void
    {
        $prestataire = $this->prestataireEnrole();

        app(FaceCheckService::class)->withdrawConsent($prestataire);

        $verdict = app(FaceCheckGate::class)->inspectProvider($prestataire);
        $this->assertFalse($verdict->allowed());
    }

    // ─────────────────────────────────────────────────────────────
    // La cadence — le cœur anti-triche
    // ─────────────────────────────────────────────────────────────

    public function test_lecheance_tombe_dans_la_fenetre_configuree(): void
    {
        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);

        // Vingt-cinq tirages releves ensemble. Sur une cadence TIREE AU SORT, savoir qu'un
        // tirage sort des bornes ne dit rien : c'est la DISTRIBUTION qui compte, et un generateur
        // decale produit plusieurs valeurs fautives dont la liste revele le motif.
        $horsBornes = [];

        for ($i = 0; $i < 25; $i++) {
            app(FaceCheckScheduler::class)->scheduleNext($profil);
            $profil->refresh();

            $heures = now()->diffInHours($profil->next_check_due_at, false);

            if ($heures < 23) {
                $horsBornes[] = "tirage #{$i} : {$heures} h — plus d un controle par 24 h";
            } elseif ($heures > 72) {
                $horsBornes[] = "tirage #{$i} : {$heures} h — plus de 3 jours sans controle";
            }
        }

        $this->assertSame([], $horsBornes, 'La cadence tiree au sort sort de ses bornes.');
    }

    /** SI L'ÉCHÉANCE EST PRÉVISIBLE, LE MODULE NE PROUVE RIEN. */
    public function test_lecheance_nest_pas_previsible(): void
    {
        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);

        $valeurs = [];
        for ($i = 0; $i < 25; $i++) {
            app(FaceCheckScheduler::class)->scheduleNext($profil);
            $valeurs[] = $profil->refresh()->next_check_due_at->getTimestamp();
        }

        $this->assertGreaterThan(5, count(array_unique($valeurs)), 'La cadence doit être tirée au sort.');
    }

    public function test_un_intervalle_inverse_ne_fait_pas_lever(): void
    {
        PlatformModule::query()->where('key', 'security.face_check')->update([
            'settings' => ['allowed_zone_ids' => [$this->zoneDuControle->id], 'face_check' => [
                'min_hours' => 48,
                'max_hours' => 12, // incohérent exprès
            ]],
        ]);
        $this->oublierLesCachesDuControleFacial();

        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);

        app(FaceCheckScheduler::class)->scheduleNext($profil);

        $this->assertNotNull($profil->refresh()->next_check_due_at);
    }

    public function test_un_nouvel_appareil_declenche_un_controle_hors_cadence(): void
    {
        $prestataire = $this->prestataireEnrole(deviceName: 'pixel-de-jean');

        // À jour : rien à faire depuis le même appareil.
        $this->assertTrue(
            app(FaceCheckGate::class)->inspectProvider($prestataire, 'pixel-de-jean')->allowed()
        );

        $verdict = app(FaceCheckGate::class)->inspectProvider($prestataire, 'un-autre-telephone');

        $this->assertSame(FaceCheckDecision::CHECK_REQUIRED, $verdict->code);
        $this->assertSame(ProviderFaceCheck::TRIGGER_RISK_DEVICE, $verdict->trigger);
    }

    public function test_un_appareil_non_renseigne_ne_fabrique_pas_de_soupcon(): void
    {
        $prestataire = $this->prestataireEnrole(deviceName: 'pixel-de-jean');

        $this->assertTrue(app(FaceCheckGate::class)->inspectProvider($prestataire, null)->allowed());
    }

    public function test_un_controle_deja_ouvert_nen_ouvre_pas_un_second(): void
    {
        $prestataire = $this->prestataireEnrole();
        $profil = app(FaceCheckService::class)->profileFor($prestataire);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        $service = app(FaceCheckService::class);
        $a = $service->openCheck($prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);
        $b = $service->openCheck($prestataire, ProviderFaceCheck::TRIGGER_INTERVAL);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ProviderFaceCheck::query()->where('user_id', $prestataire->id)->count());
    }

    /** Un prestataire enrôlé, consentant, à jour. */
    private function prestataireEnrole(?string $deviceName = null): User
    {
        $prestataire = $this->prestataireSoumis();

        $service = app(FaceCheckService::class);
        $service->enroll($prestataire, 'reference-bytes#face:jean', 'image/jpeg', true, [
            'ip' => '10.0.0.1',
            'device_name' => $deviceName,
        ]);

        if ($deviceName !== null) {
            // Un premier contrôle réussi depuis cet appareil : c'est lui qui sert de référence.
            $profil = $service->profileFor($prestataire);
            $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

            $controle = $service->openCheck($prestataire, ProviderFaceCheck::TRIGGER_INTERVAL, [
                'device_name' => $deviceName,
            ]);
            $service->submit($controle, 'selfie-bytes#face:jean', 'image/jpeg', [
                'device_name' => $deviceName,
            ]);
        }

        return $prestataire->refresh();
    }
}

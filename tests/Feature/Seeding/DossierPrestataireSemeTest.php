<?php

namespace Tests\Feature\Seeding;

use App\Models\OnboardingProgress;
use App\Models\ProviderPresence;
use App\Models\User;
use Database\Seeders\DispatchDemoSeeder;
use Database\Seeders\ReferencePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN PRESTATAIRE SEMÉ DOIT POUVOIR ENTRER CHEZ LUI, pas seulement recevoir des offres.
 *
 * DEUX SOURCES RÉPONDENT À « CE PRESTATAIRE EST-IL VÉRIFIÉ ? », ET LES DEUX CÔTÉS DE L'APPLICATION
 * N'INTERROGENT PAS LA MÊME : le dispatch lit `provider_profiles.verification_status`, le parcours
 * d'inscription lit `kyc_verifications`. Les seeders ne posaient que la première. Le moteur
 * fonctionnait donc parfaitement — offres, escalade, acceptation — pendant que le prestataire, lui,
 * se heurtait à « Vérification KYC non approuvée » dès la connexion. La démonstration s'arrêtait à
 * l'écran d'accueil, et le message accusait le KYC là où le trou était dans le semis.
 *
 * Ce test sème POUR DE VRAI le scénario de démonstration et vérifie les deux moitiés à la fois :
 * candidat pour le moteur, et dossier bouclé pour l'espace. C'est le seul moyen de le savoir —
 * `migrate:fresh --seed` ne dit rien de ce qu'un humain rencontrera après s'être connecté.
 */
class DossierPrestataireSemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferencePlatformSeeder::class);
        $this->seed(DispatchDemoSeeder::class);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function prestatairesDeDemonstration()
    {
        return User::query()
            ->whereIn('email', ['demo.proche@brio.test', 'demo.moyen@brio.test', 'demo.loin@brio.test'])
            ->get();
    }

    #[Test]
    public function les_trois_prestataires_de_demonstration_existent(): void
    {
        $this->assertCount(3, $this->prestatairesDeDemonstration());
    }

    #[Test]
    public function leur_identite_est_verifiee_des_DEUX_cotes(): void
    {
        foreach ($this->prestatairesDeDemonstration() as $prestataire) {
            // Côté dispatch : le drapeau du profil.
            $this->assertSame(
                'verified',
                $prestataire->providerProfile?->verification_status,
                "{$prestataire->email} : le dispatch le refuserait.",
            );

            // Côté parcours d'inscription : une vérification réellement approuvée.
            $this->assertDatabaseHas('kyc_verifications', [
                'user_id' => $prestataire->id,
                'decision' => 'approved',
                'status' => 'clear',
            ]);
        }
    }

    #[Test]
    public function leur_dossier_d_inscription_est_boucle(): void
    {
        foreach ($this->prestatairesDeDemonstration() as $prestataire) {
            $progression = OnboardingProgress::query()->where('user_id', $prestataire->id)->first();

            $this->assertNotNull($progression, "{$prestataire->email} n’a pas de parcours d’inscription.");
            $this->assertSame(
                OnboardingProgress::STATUS_COMPLETED,
                $progression->status,
                "{$prestataire->email} reste bloqué à l’étape « {$progression->current_step_code} ».",
            );
        }
    }

    #[Test]
    public function ils_restent_de_vrais_candidats_pour_le_moteur(): void
    {
        foreach ($this->prestatairesDeDemonstration() as $prestataire) {
            // Boucler le dossier ne doit RIEN coûter au dispatch : métier déclaré, zone, et une
            // présence en ligne avec un battement frais restent les quatre conditions du moteur.
            $this->assertGreaterThan(0, $prestataire->trades()->count(), "{$prestataire->email} : aucun métier.");

            $presence = ProviderPresence::query()->where('provider_user_id', $prestataire->id)->first();

            $this->assertNotNull($presence);
            $this->assertSame(ProviderPresence::STATUS_ONLINE, $presence->status);
            $this->assertNotNull($presence->heartbeat_at);
        }
    }

    #[Test]
    public function rejouer_le_semis_ne_casse_rien(): void
    {
        // Rejouer ce seeder est le geste courant : c'est ainsi qu'on remet les prestataires en
        // ligne après une pause, le battement expirant au bout de cinq minutes.
        $this->seed(DispatchDemoSeeder::class);

        $this->assertCount(3, $this->prestatairesDeDemonstration());

        foreach ($this->prestatairesDeDemonstration() as $prestataire) {
            $this->assertSame(
                1,
                \App\Models\KycVerification::query()->where('user_id', $prestataire->id)->count(),
                "{$prestataire->email} : une vérification d’identité en double à chaque semis.",
            );
        }
    }
}

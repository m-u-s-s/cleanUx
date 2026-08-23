<?php

namespace Database\Seeders;

use App\Enums\ProviderType;
use App\Models\AvailabilitySlot;
use App\Models\EmployeeZoneAssignment;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Database\Seeders\Concerns\BoucleLeDossierPrestataire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** LE SCÉNARIO DE DÉMONSTRATION DU MOTEUR DE RÉPARTITION. */
class DispatchDemoSeeder extends Seeder
{
    use BoucleLeDossierPrestataire;

    /** Grand-Place de Bruxelles — l'adresse de démonstration. */
    private const LAT = 50.8467;

    private const LNG = 4.3525;

    public function run(): void
    {
        $zone = $this->zoneDeDemonstration();

        if (! $zone) {
            $this->command?->warn('⚠️ Aucune zone active : DispatchDemoSeeder ignoré.');

            return;
        }

        $metier = $this->metierEnImmediat($zone);

        if (! $metier) {
            $this->command?->warn('⚠️ Aucun métier ouvert en immédiat : DispatchDemoSeeder ignoré.');

            return;
        }

        $prestataires = [
            ['email' => 'demo.proche@brio.test', 'name' => 'Démo — le plus proche', 'phone' => '+32470000101', 'lat' => 50.8497, 'lng' => 4.3560],
            ['email' => 'demo.moyen@brio.test', 'name' => 'Démo — à 1 km', 'phone' => '+32470000102', 'lat' => 50.8560, 'lng' => 4.3600],
            ['email' => 'demo.loin@brio.test', 'name' => 'Démo — à 3 km', 'phone' => '+32470000103', 'lat' => 50.8730, 'lng' => 4.3700],
        ];

        foreach ($prestataires as $donnees) {
            $this->prestataireEnLigne($donnees, $metier, $zone);
        }

        $this->command?->info(sprintf(
            '✅ Démonstration du dispatch : 3 prestataires « %s » en ligne autour de %s (zone %s).',
            $metier->name,
            'la Grand-Place',
            $zone->name,
        ));
    }

    private function zoneDeDemonstration(): ?ServiceZone
    {
        // La zone de Bruxelles d'abord — c'est autour d'elle que les positions sont posées. À
        // défaut, n'importe quelle zone active : mieux vaut une démonstration décalée qu'aucune.
        return ServiceZone::query()->where('slug', 'zone-bruxelles')->where('status', 'active')->first()
            ?? ServiceZone::query()->where('status', 'active')->orderBy('priority')->first();
    }

    private function metierEnImmediat(ServiceZone $zone): ?Trade
    {
        $ligne = TradeZonePricing::query()
            ->where('service_zone_id', $zone->id)
            ->where('is_active', true)
            ->where('asap_enabled', true)
            ->first();

        if (! $ligne) {
            // Aucun métier n'accepte l'immédiat ici : on en OUVRE un, parmi ceux que leur nature autorise.
            $candidat = TradeZonePricing::query()
                ->where('service_zone_id', $zone->id)
                ->where('is_active', true)
                ->whereIn('trade_id', Trade::query()->where('allows_asap', true)->select('id'))
                ->first();

            if (! $candidat) {
                return null;
            }

            $candidat->update(['asap_enabled' => true]);
            $ligne = $candidat;
        }

        return Trade::find($ligne->trade_id);
    }

    /** @param  array{email: string, name: string, phone: string, lat: float, lng: float}  $donnees */
    private function prestataireEnLigne(array $donnees, Trade $metier, ServiceZone $zone): void
    {
        $utilisateur = User::query()->updateOrCreate(
            ['email' => $donnees['email']],
            [
                'name' => $donnees['name'],
                'password' => Hash::make((string) config('brio.seed.password')),
                'role' => User::ROLE_EMPLOYE,
                'is_active' => true,
                // `ProfileCompleteValidator` exige nom, email ET téléphone : sans numéro, la
                // toute première étape du dossier refuse, et rien à l'écran ne dit laquelle.
                'phone' => $donnees['phone'],
                'email_verified_at' => now(),
            ],
        );

        // La zone principale est hors de la liste blanche de `User` à dessein : elle passe par
        // `ProviderCoverageWriter`. Dans le tableau ci-dessus, elle était écartée en silence — et
        // les prestataires de démonstration naissaient donc SANS zone principale, alors que tout
        // le filtrage par zone de la console d'administration s'appuie dessus.
        $utilisateur->forceFill(['primary_service_zone_id' => $zone->id])->save();

        // VÉRIFIÉ, et pas seulement actif.
        ProviderProfile::query()->updateOrCreate(
            ['user_id' => $utilisateur->id],
            [
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'active',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'current_lat' => $donnees['lat'],
                'current_lng' => $donnees['lng'],
            ],
        );

        // EN LIGNE AVEC UNE POSITION FRAÎCHE : c'est `provider_presence` que lit le dispatch
        // immédiat, pas le miroir binaire du profil.
        ProviderPresence::query()->updateOrCreate(
            ['provider_user_id' => $utilisateur->id],
            [
                'status' => ProviderPresence::STATUS_ONLINE,
                'current_lat' => $donnees['lat'],
                'current_lng' => $donnees['lng'],
                'heartbeat_at' => now(),
                'last_online_at' => now(),
            ],
        );

        $utilisateur->trades()->syncWithoutDetaching([$metier->id => ['is_primary' => true]]);

        EmployeeZoneAssignment::query()->updateOrCreate(
            ['user_id' => $utilisateur->id, 'service_zone_id' => $zone->id],
            ['assignment_type' => 'primary', 'is_active' => true, 'status' => 'active', 'coverage_priority' => 100],
        );

        // DISPONIBLE À L'AGENDA, PAS SEULEMENT EN LIGNE.
        foreach (range(1, 5) as $jourDeSemaine) {
            foreach ([['09:00:00', '12:00:00'], ['14:00:00', '17:00:00']] as [$debut, $fin]) {
                AvailabilitySlot::query()->updateOrCreate(
                    [
                        'provider_user_id' => $utilisateur->id,
                        'weekday' => $jourDeSemaine,
                        'start_time' => $debut,
                    ],
                    [
                        'end_time' => $fin,
                        'is_active' => true,
                        'timezone' => 'Europe/Brussels',
                        'metadata' => ['seeded' => true],
                    ],
                );
            }
        }

        $this->boucleLeDossier($utilisateur);
    }
}

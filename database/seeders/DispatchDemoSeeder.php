<?php

namespace Database\Seeders;

use App\Enums\ProviderType;
use App\Models\EmployeeZoneAssignment;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LE SCÉNARIO DE DÉMONSTRATION DU MOTEUR DE RÉPARTITION.
 *
 * Après `migrate:fresh --seed`, on doit pouvoir dérouler À LA MAIN, sans toucher au code :
 *
 *   commande immédiate → modale 20 s chez le plus proche → refus → escalade au suivant →
 *   acceptation → mission.
 *
 * C'EST LE PIÈGE HABITUEL DE CE DÉPÔT : un module complet dont personne ne crée les lignes. Le
 * dispatch immédiat exige quatre choses simultanément — un métier ouvert en immédiat dans la zone,
 * des prestataires VÉRIFIÉS, déclarés sur ce métier, et EN LIGNE avec une position fraîche. Il
 * suffit qu'une seule manque pour que la recherche s'épuise en silence, et rien à l'écran ne dit
 * laquelle.
 *
 * LES POSITIONS SONT ÉCHELONNÉES autour de l'adresse de démonstration : 400 m, 1,2 km, 3 km. C'est
 * ce qui rend l'ordre d'escalade VISIBLE — trois prestataires au même endroit donneraient un ordre
 * arbitraire, et la démonstration ne montrerait pas que la proximité prime.
 *
 * LE BATTEMENT EST POSÉ À `now()`. Il expire au bout de cinq minutes
 * (`dispatch.position_freshness_minutes`) : après une longue pause, rejouer ce seeder suffit à
 * remettre tout le monde en ligne.
 */
class DispatchDemoSeeder extends Seeder
{
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
            ['email' => 'demo.proche@brio.test', 'name' => 'Démo — le plus proche', 'lat' => 50.8497, 'lng' => 4.3560],
            ['email' => 'demo.moyen@brio.test', 'name' => 'Démo — à 1 km', 'lat' => 50.8560, 'lng' => 4.3600],
            ['email' => 'demo.loin@brio.test', 'name' => 'Démo — à 3 km', 'lat' => 50.8730, 'lng' => 4.3700],
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
            /*
             * Aucun métier n'accepte l'immédiat ici : on en OUVRE un, parmi ceux que leur nature
             * autorise. Une démonstration qui exige d'aller cocher une case en base avant de
             * pouvoir montrer quoi que ce soit n'est pas une démonstration.
             */
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

    /** @param  array{email: string, name: string, lat: float, lng: float}  $donnees */
    private function prestataireEnLigne(array $donnees, Trade $metier, ServiceZone $zone): void
    {
        $utilisateur = User::query()->updateOrCreate(
            ['email' => $donnees['email']],
            [
                'name' => $donnees['name'],
                'password' => Hash::make((string) config('brio.seed.password')),
                'role' => User::ROLE_EMPLOYE,
                'is_active' => true,
                'primary_service_zone_id' => $zone->id,
                'email_verified_at' => now(),
            ],
        );

        /*
         * VÉRIFIÉ, et pas seulement actif. Le KYC est un blocage strict du dispatch : un
         * prestataire `pending` ne reçoit aucune offre, et la démonstration s'arrêterait sur une
         * recherche épuisée sans que rien ne dise pourquoi.
         */
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
    }
}

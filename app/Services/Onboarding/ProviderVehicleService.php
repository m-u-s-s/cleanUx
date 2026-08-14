<?php

namespace App\Services\Onboarding;

use App\Models\FleetVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * LE VÉHICULE DU PRESTATAIRE, ET SON ÂGE.
 *
 * La règle « moins de quatre ans » est celle qu'appliquent Uber, Bolt et Heetch dans la plupart des
 * villes. Elle ne se vérifie pas à l'œil sur une photo de carte grise : elle se CALCULE, depuis la
 * date de première immatriculation, et elle se re-vérifie — parce qu'une voiture vieillit. Un
 * contrôle passé une fois à l'inscription laisserait rouler, trois ans plus tard, exactement ce
 * qu'on prétendait interdire.
 *
 * IL RÉUTILISE `fleet_vehicles`. La table porte déjà marque, modèle, plaque, année, pays et date de
 * première immatriculation, et un scanner quotidien sait déjà faire expirer ce qui doit l'être. En
 * créer une seconde pour les mêmes données aurait produit deux inventaires de véhicules qui
 * finiraient par se contredire — le défaut dominant de ce dépôt.
 *
 * CE QU'IL NE FAIT PAS : décider qui reçoit des missions. Cette question se pose au dispatch, et
 * elle s'y pose métier par métier.
 */
class ProviderVehicleService
{
    /** Un prestataire ne déclare qu'UN véhicule : celui avec lequel il conduit. */
    public function vehiculeDe(User $user): ?FleetVehicle
    {
        return FleetVehicle::query()
            ->where('current_provider_id', $user->id)
            ->whereNot('status', FleetVehicle::STATUS_RETIRED)
            ->latest('id')
            ->first();
    }

    /**
     * Déclare ou met à jour le véhicule du prestataire.
     *
     * @param  array{plate: string, brand?: ?string, model?: ?string, vehicle_type?: ?string, registered_at?: ?string, registered_country?: ?string}  $donnees
     */
    public function declarer(User $user, array $donnees): FleetVehicle
    {
        $existant = $this->vehiculeDe($user);

        $attributs = [
            'plate' => strtoupper(trim($donnees['plate'])),
            'brand' => $donnees['brand'] ?? null,
            'model' => $donnees['model'] ?? null,
            'vehicle_type' => $donnees['vehicle_type'] ?? 'car',
            'registered_at' => $donnees['registered_at'] ?? null,
            'registered_country' => $donnees['registered_country'] ?? null,
            'current_provider_id' => $user->id,
            /*
             * Le véhicule appartient à la SOCIÉTÉ quand le prestataire est salarié : c'est elle qui
             * l'assure et le renouvelle. Le rattacher au seul conducteur ferait disparaître la
             * flotte de l'espace société le jour où il change d'employeur.
             */
            'organization_account_id' => $user->providerProfile?->organization_account_id,
        ];

        if ($existant) {
            $existant->update($attributs);

            return $existant->fresh();
        }

        return FleetVehicle::create($attributs + [
            'code' => FleetVehicle::generateCode(),
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
    }

    /**
     * L'âge du véhicule en années, depuis sa PREMIÈRE immatriculation.
     *
     * L'année du modèle (`year`) ne sert que de repli, et elle est volontairement plus indulgente :
     * elle ne dit pas quand la voiture a été mise en circulation, seulement de quelle génération
     * elle est. Refuser sur cette base seule reprocherait au prestataire une donnée approximative
     * que la plateforme a elle-même acceptée.
     */
    public function ageEnAnnees(FleetVehicle $vehicule, ?Carbon $maintenant = null): ?float
    {
        $maintenant ??= Carbon::now();

        if ($vehicule->registered_at) {
            // En jours divisés par l'année julienne : `diffInYears` tronque à l'entier, et une
            // voiture de quatre ans et onze mois passerait alors pour une voiture de quatre ans —
            // exactement le cas que la limite est censée attraper.
            return round($vehicule->registered_at->diffInDays($maintenant) / 365.25, 2);
        }

        if ($vehicule->year) {
            return round($maintenant->year - (int) $vehicule->year, 2);
        }

        return null;
    }

    /**
     * L'âge maximal toléré, en années.
     *
     * Le plafond varie d'une ville à l'autre chez tous les concurrents ; la surcharge par pays
     * permet de le dire sans déploiement.
     */
    public function limiteDAge(?string $codePays = null): int
    {
        $parPays = (array) Config::get('fleet_v2.taxi_rules.by_country', []);
        $general = (int) Config::get('fleet_v2.taxi_rules.max_vehicle_age_years', 4);

        if ($codePays !== null && isset($parPays[strtoupper($codePays)])) {
            return (int) $parPays[strtoupper($codePays)];
        }

        return $general;
    }

    /**
     * Le dossier VÉHICULE du prestataire, avec son verdict.
     *
     * Rendu comme un état complet plutôt qu'un booléen : un refus doit pouvoir DIRE ce qui cloche —
     * « pas de véhicule déclaré », « date d'immatriculation manquante », « six ans, la limite est à
     * quatre ». Un `false` nu laisse le prestataire deviner, et c'est ainsi qu'on se retrouve avec
     * un compte actif qui ne reçoit rien sans que personne ne sache pourquoi.
     *
     * @return array{requis: bool, vehicule: FleetVehicle|null, age: float|null, limite: int, conforme: bool, motif: string|null}
     */
    public function dossier(User $user): array
    {
        $requis = $this->exigePourCePrestataire($user);
        $vehicule = $this->vehiculeDe($user);
        $limite = $this->limiteDAge($vehicule?->registered_country);

        if (! $requis) {
            return [
                'requis' => false,
                'vehicule' => $vehicule,
                'age' => $vehicule ? $this->ageEnAnnees($vehicule) : null,
                'limite' => $limite,
                'conforme' => true,
                'motif' => null,
            ];
        }

        if (! $vehicule) {
            return [
                'requis' => true, 'vehicule' => null, 'age' => null, 'limite' => $limite,
                'conforme' => false, 'motif' => 'Aucun véhicule déclaré.',
            ];
        }

        $age = $this->ageEnAnnees($vehicule);

        if ($age === null) {
            return [
                'requis' => true, 'vehicule' => $vehicule, 'age' => null, 'limite' => $limite,
                'conforme' => false,
                'motif' => 'Date de première immatriculation manquante : elle figure sur la carte grise.',
            ];
        }

        if ($age > $limite) {
            return [
                'requis' => true, 'vehicule' => $vehicule, 'age' => $age, 'limite' => $limite,
                'conforme' => false,
                'motif' => sprintf(
                    'Véhicule de %s ans : la limite est de %d ans pour ce métier.',
                    str_replace('.', ',', (string) round($age, 1)),
                    $limite,
                ),
            ];
        }

        return [
            'requis' => true, 'vehicule' => $vehicule, 'age' => $age, 'limite' => $limite,
            'conforme' => true, 'motif' => null,
        ];
    }

    /** Ce prestataire exerce-t-il un métier sous règles taxi ? */
    public function exigePourCePrestataire(User $user): bool
    {
        return $user->trades()
            ->where('trades.taxi_rules', true)
            ->exists();
    }

    /**
     * La pièce qui atteste l'immatriculation est-elle déposée et non refusée ?
     *
     * Le calcul d'âge repose sur une date que le prestataire a saisie lui-même. La carte grise est
     * ce qui la rend opposable : sans elle, on refuserait des véhicules conformes et on accepterait
     * des dates inventées.
     */
    public function carteGriseDeposee(User $user): bool
    {
        return ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->where('document_type', ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION)
            ->whereNot('status', ProviderOnboardingDocument::STATUS_REJECTED)
            ->exists();
    }

    /**
     * Les métiers du prestataire qui déclenchent les règles taxi — pour les nommer dans un refus.
     *
     * @return list<string>
     */
    public function metiersConcernes(User $user): array
    {
        return $user->trades()
            ->where('trades.taxi_rules', true)
            ->get(['trades.id', 'trades.name'])
            ->map(fn (Trade $trade) => (string) $trade->name)
            ->values()
            ->all();
    }
}

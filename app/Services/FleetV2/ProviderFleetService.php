<?php

namespace App\Services\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\OrganizationMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LA FLOTTE VUE PAR LA SOCIÉTÉ QUI LA POSSÈDE (E27).
 *
 * Fleet v2 est complet et entièrement piloté par l'administration de la PLATEFORME. Une société qui
 * a trois camionnettes et deux monobrosses ne pouvait ni les déclarer, ni savoir laquelle est en
 * maintenance, ni voir que le permis d'un de ses exécutants expire dans deux semaines — alors que le
 * blocage de mission sur certification expirée, lui, fonctionne déjà et va lui refuser une
 * assignation sans qu'elle comprenne pourquoi.
 *
 * TROIS PÉRIMÈTRES, ET C'EST LE CŒUR DE CETTE CLASSE.
 *
 * Les véhicules et équipements QU'ELLE POSSÈDE — `organization_account_id`. Ceux de la plateforme
 * (colonne à `null`) ne lui appartiennent pas et ne se montrent pas : les inclure lui donnerait un
 * droit de regard sur le matériel d'une concurrente.
 *
 * Ce qui est ASSIGNÉ à ses exécutants, même sans lui appartenir. Une société qui loue un camion à la
 * plateforme doit savoir qui l'a et jusqu'à quand — c'est elle qui répond du retour.
 *
 * Les certifications DE SES EXÉCUTANTS. Le permis poids lourd d'un salarié est une donnée
 * d'employeur, pas de plateforme.
 *
 * LA PÉREMPTION SE DIT AVANT, PAS LE JOUR MÊME. Une certification qui expire dans trois jours est
 * annoncée comme telle : découvrir l'expiration au moment où le moteur refuse l'assignation, c'est
 * la découvrir trop tard.
 */
class ProviderFleetService
{
    /** À partir de combien de jours une échéance mérite d'être annoncée. */
    public const PREAVIS_JOURS = 30;

    /**
     * Les véhicules de la société : les siens, plus ceux qu'on lui a confiés.
     *
     * @return Collection<int, FleetVehicle>
     */
    public function vehicules(int $organisationId): Collection
    {
        $confies = $this->assignationsActives($organisationId)
            ->pluck('vehicle_id')
            ->filter()
            ->unique();

        return FleetVehicle::query()
            ->where(function ($q) use ($organisationId, $confies) {
                $q->where('organization_account_id', $organisationId)
                    ->orWhereIn('id', $confies);
            })
            ->with('currentProvider:id,name')
            ->orderBy('plate')
            ->get();
    }

    /**
     * Les équipements de la société : les siens, plus ceux qu'on lui a confiés.
     *
     * @return Collection<int, FleetEquipment>
     */
    public function equipements(int $organisationId): Collection
    {
        $confies = $this->assignationsActives($organisationId)
            ->pluck('equipment_id')
            ->filter()
            ->unique();

        return FleetEquipment::query()
            ->where(function ($q) use ($organisationId, $confies) {
                $q->where('organization_account_id', $organisationId)
                    ->orWhereIn('id', $confies);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Les certifications des exécutants de la société, et de son matériel.
     *
     * @return Collection<int, FleetCertification>
     */
    public function certifications(int $organisationId): Collection
    {
        $membres = $this->membres($organisationId);
        $vehicules = FleetVehicle::query()
            ->where('organization_account_id', $organisationId)
            ->pluck('id');
        $equipements = FleetEquipment::query()
            ->where('organization_account_id', $organisationId)
            ->pluck('id');

        return FleetCertification::query()
            ->where(function ($q) use ($membres, $vehicules, $equipements) {
                $q->where(fn ($sq) => $sq
                    ->where('subject_type', FleetCertification::SUBJECT_PROVIDER)
                    ->whereIn('subject_id', $membres))
                    ->orWhere(fn ($sq) => $sq
                        ->where('subject_type', FleetCertification::SUBJECT_VEHICLE)
                        ->whereIn('subject_id', $vehicules))
                    ->orWhere(fn ($sq) => $sq
                        ->where('subject_type', FleetCertification::SUBJECT_EQUIPMENT)
                        ->whereIn('subject_id', $equipements));
            })
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Ce qui expire bientôt — ou a déjà expiré.
     *
     * C'EST LA SEULE LECTURE QUI CHANGE QUELQUE CHOSE. Le reste est un inventaire ; celle-ci évite
     * qu'une assignation soit refusée un matin sans que personne ne sache pourquoi.
     *
     * @return Collection<int, FleetCertification>
     */
    public function echeances(int $organisationId, ?int $preavisJours = null): Collection
    {
        $limite = Carbon::now()->addDays($preavisJours ?? self::PREAVIS_JOURS);

        return $this->certifications($organisationId)
            ->filter(fn (FleetCertification $certification) => $certification->expires_at !== null
                && $certification->status !== FleetCertification::STATUS_REVOKED
                && $certification->expires_at->lessThanOrEqualTo($limite))
            ->values();
    }

    /**
     * Déclarer un véhicule à soi.
     *
     * @param  array<string, mixed>  $attributs
     */
    public function declarerUnVehicule(int $organisationId, array $attributs): FleetVehicle
    {
        /** @var FleetVehicle $vehicule */
        $vehicule = FleetVehicle::query()->create(array_merge($attributs, [
            'code' => FleetVehicle::generateCode(),
            'organization_account_id' => $organisationId,
            'status' => $attributs['status'] ?? FleetVehicle::STATUS_AVAILABLE,
            /*
             * `vehicle_type` EST NOT NULL EN BASE, et la plupart des sociétés de services ne
             * roulent qu'en camionnette : imposer le choix ferait buter la déclaration sur un champ
             * qui, neuf fois sur dix, aurait la même valeur. Le défaut se corrige, l'absence de
             * formulaire non.
             */
            'vehicle_type' => $attributs['vehicle_type'] ?? 'van',
        ]));

        return $vehicule;
    }

    /**
     * Déclarer un équipement à soi.
     *
     * @param  array<string, mixed>  $attributs
     */
    public function declarerUnEquipement(int $organisationId, array $attributs): FleetEquipment
    {
        /** @var FleetEquipment $equipement */
        $equipement = FleetEquipment::query()->create(array_merge($attributs, [
            'code' => FleetEquipment::generateCode(),
            'organization_account_id' => $organisationId,
            'status' => $attributs['status'] ?? 'available',
            // NOT NULL en base, comme `vehicle_type` : `tool` est le cas ordinaire.
            'equipment_type' => $attributs['equipment_type'] ?? 'tool',
        ]));

        return $equipement;
    }

    /**
     * Ce véhicule appartient-il à cette société — ou lui est-il confié ?
     *
     * Le scoping fait partie de la LECTURE : un identifiant forgé ne doit jamais charger le camion
     * d'une autre société, faute de quoi la différence entre un 403 et un 404 dirait qu'il existe.
     */
    public function vehiculeDeLaSociete(int $organisationId, int $vehiculeId): ?FleetVehicle
    {
        return $this->vehicules($organisationId)->firstWhere('id', $vehiculeId);
    }

    /** @return Collection<int, FleetAssignment> */
    protected function assignationsActives(int $organisationId): Collection
    {
        return FleetAssignment::query()
            ->where('status', FleetAssignment::STATUS_ACTIVE)
            ->whereIn('provider_user_id', $this->membres($organisationId))
            ->get(['id', 'vehicle_id', 'equipment_id', 'provider_user_id']);
    }

    /** @return Collection<int, int> */
    protected function membres(int $organisationId): Collection
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);
    }
}

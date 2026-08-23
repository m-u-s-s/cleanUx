<?php

namespace App\Services\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\OrganizationMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** LA FLOTTE VUE PAR LA SOCIÉTÉ QUI LA POSSÈDE (E27). */
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
     * Ce qui expire bientôt — ou a déjà expiré. C'EST LA SEULE LECTURE QUI CHANGE QUELQUE CHOSE.
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
            // `vehicle_type` EST NOT NULL EN BASE, et la plupart des sociétés de services ne roulent qu'en camionnette : imposer le choix ferait buter la déclaration sur un champ qui, neuf fois sur dix, aurait la même valeur.
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

    /** Ce véhicule appartient-il à cette société — ou lui est-il confié ? */
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

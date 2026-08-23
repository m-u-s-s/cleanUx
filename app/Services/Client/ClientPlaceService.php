<?php

namespace App\Services\Client;

use App\Models\ClientPlace;
use App\Models\User;
use App\Services\OrderEngine\ZonePricingResolver;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** LE CARNET DE LIEUX D'UN CLIENT (E2). */
class ClientPlaceService
{
    /** Au-delà, ce n'est plus un carnet mais un fichier — et le sélecteur devient inutilisable. */
    public const MAXIMUM_LIEUX = 25;

    /**
     * Enregistrer un lieu.
     *
     * @param  array<string, mixed>  $attributs
     *
     * @throws DomainException
     */
    public function enregistrer(User $client, array $attributs): ClientPlace
    {
        $actifs = $this->pour($client)->count();

        if ($actifs >= self::MAXIMUM_LIEUX) {
            throw new DomainException(sprintf(
                'Votre carnet contient déjà %d lieux. Archivez-en un avant d’en ajouter.',
                self::MAXIMUM_LIEUX,
            ));
        }

        $attributs['user_id'] = $client->id;
        $attributs = $this->avecLaZoneResolue($attributs);

        // Le PREMIER lieu devient le défaut sans qu'on le demande : un carnet dont aucun lieu n'est
        // par défaut ne pré-remplit rien, ce qui est exactement le problème qu'il devait résoudre.
        $attributs['is_default'] = (bool) ($attributs['is_default'] ?? false) || $actifs === 0;

        return DB::transaction(function () use ($client, $attributs) {
            /** @var ClientPlace $lieu */
            $lieu = ClientPlace::query()->create($attributs);

            if ($lieu->is_default) {
                $this->demettreLesAutres($client, $lieu->id);
            }

            return $lieu;
        });
    }

    /**
     * Modifier un lieu.
     *
     * @param  array<string, mixed>  $attributs
     */
    public function modifier(ClientPlace $lieu, array $attributs): ClientPlace
    {
        // L'adresse a pu changer : la zone se recalcule, sinon un déménagement garderait la grille
        // tarifaire de l'ancienne ville.
        $attributs = $this->avecLaZoneResolue($attributs, $lieu);

        return DB::transaction(function () use ($lieu, $attributs) {
            $lieu->fill($attributs)->save();

            if ($lieu->is_default) {
                $this->demettreLesAutres($lieu->user, $lieu->id);
            }

            return $lieu->fresh();
        });
    }

    public function definirParDefaut(ClientPlace $lieu): ClientPlace
    {
        return DB::transaction(function () use ($lieu) {
            $lieu->forceFill(['is_default' => true])->save();
            $this->demettreLesAutres($lieu->user, $lieu->id);

            return $lieu->fresh();
        });
    }

    /** Archiver — jamais supprimer. */
    public function archiver(ClientPlace $lieu): ClientPlace
    {
        return DB::transaction(function () use ($lieu) {
            $lieu->forceFill(['archived_at' => now(), 'is_default' => false])->save();

            $client = $lieu->user;

            if ($client === null) {
                return $lieu->fresh();
            }

            $restants = $this->pour($client);

            if ($restants->isNotEmpty() && $restants->firstWhere('is_default', true) === null) {
                $this->definirParDefaut($restants->first());
            }

            return $lieu->fresh();
        });
    }

    /**
     * Les lieux ACTIFS d'un client, le défaut en tête.
     *
     * @return Collection<int, ClientPlace>
     */
    public function pour(User $client): Collection
    {
        return ClientPlace::query()
            ->where('user_id', $client->id)
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    /** Le lieu par défaut, ou `null` — celui qui pré-remplit le parcours de commande. */
    public function parDefaut(User $client): ?ClientPlace
    {
        return $this->pour($client)->firstWhere('is_default', true);
    }

    /** Un lieu QUI M'APPARTIENT, ou `null`. */
    public function lieuDuClient(User $client, int $lieuId): ?ClientPlace
    {
        return ClientPlace::query()
            ->where('user_id', $client->id)
            ->find($lieuId);
    }

    /**
     * @param  array<string, mixed>  $attributs
     * @return array<string, mixed>
     */
    protected function avecLaZoneResolue(array $attributs, ?ClientPlace $lieu = null): array
    {
        $codePostal = $attributs['postal_code'] ?? $lieu?->postal_code;
        $ville = $attributs['city'] ?? $lieu?->city;

        if (! $codePostal && ! $ville) {
            return $attributs;
        }

        try {
            $attributs['service_zone_id'] = app(ZonePricingResolver::class)
                ->resolveZone($codePostal, $ville)?->id;
        } catch (\Throwable $e) {
            // SOFT-FAIL DÉLIBÉRÉ.
            report($e);
        }

        return $attributs;
    }

    protected function demettreLesAutres(?User $client, int $sauf): void
    {
        if ($client === null) {
            return;
        }

        ClientPlace::query()
            ->where('user_id', $client->id)
            ->whereKeyNot($sauf)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}

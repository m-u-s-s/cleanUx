<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Mission;
use App\Models\User;
use App\Services\Organizations\OrganizationNotifier;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** LE STOCK DE CONSOMMABLES D'UNE SOCIÉTÉ (E23), ET CE QU'ON Y PRÉLÈVE SUR PLACE (F7). */
class InventoryService
{
    public function __construct(
        protected OrganizationNotifier $notifier,
    ) {}

    /**
     * Réceptionner du stock.
     *
     * @throws DomainException
     */
    public function receptionner(InventoryItem $item, int $quantite, ?User $auteur = null, ?string $motif = null): InventoryItem
    {
        if ($quantite <= 0) {
            throw new DomainException('Une réception ajoute du stock : la quantité doit être positive.');
        }

        return $this->enregistrer($item, InventoryMovement::TYPE_RECEPTION, $quantite, $auteur, null, $motif);
    }

    /**
     * Prélever pour une intervention (F7).
     *
     * @throws DomainException
     */
    public function consommer(
        InventoryItem $item,
        int $quantite,
        ?User $auteur = null,
        ?Mission $mission = null,
        ?string $motif = null,
    ): InventoryItem {
        if ($quantite <= 0) {
            throw new DomainException('Une consommation retire du stock : indiquez une quantité positive.');
        }

        return $this->enregistrer($item, InventoryMovement::TYPE_CONSUMPTION, -$quantite, $auteur, $mission, $motif);
    }

    /**
     * Corriger un écart d'inventaire — casse, perte, recomptage.
     *
     * @throws DomainException
     */
    public function ajuster(InventoryItem $item, int $delta, User $auteur, string $motif): InventoryItem
    {
        if ($delta === 0) {
            throw new DomainException('Un ajustement de zéro ne corrige rien.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Dites pourquoi le stock change : c’est tout l’intérêt d’un ajustement.');
        }

        return $this->enregistrer($item, InventoryMovement::TYPE_ADJUSTMENT, $delta, $auteur, null, trim($motif));
    }

    /**
     * Les articles sous leur seuil, pour l'écran de réappro.
     *
     * @return Collection<int, InventoryItem>
     */
    public function aReapprovisionner(int $organizationAccountId, ?int $agencyId = null): Collection
    {
        return InventoryItem::query()
            ->where('organization_account_id', $organizationAccountId)
            ->when($agencyId, fn ($q) => $q->where('provider_agency_id', $agencyId))
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'reorder_threshold')
            ->orderBy('name')
            ->get();
    }

    /** Le cœur : un mouvement, puis le compteur qui en découle. VERROUILLÉ PENDANT LA TRANSACTION. */
    protected function enregistrer(
        InventoryItem $item,
        string $type,
        int $delta,
        ?User $auteur,
        ?Mission $mission,
        ?string $motif,
    ): InventoryItem {
        return DB::transaction(function () use ($item, $type, $delta, $auteur, $mission, $motif) {
            /** @var InventoryItem $verrouille */
            $verrouille = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $avant = (int) $verrouille->quantity;
            $apres = $avant + $delta;

            if ($apres < 0) {
                throw new DomainException(sprintf(
                    'Il ne reste que %d %s en stock.',
                    $avant,
                    $verrouille->unit,
                ));
            }

            InventoryMovement::query()->create([
                'inventory_item_id' => $verrouille->id,
                'user_id' => $auteur?->id,
                'mission_id' => $mission?->id,
                'type' => $type,
                'quantity' => $delta,
                'reason' => $motif,
            ]);

            $verrouille->forceFill(['quantity' => $apres])->save();

            $this->alerterSiFranchissement($verrouille, $avant, $apres);

            return $verrouille->fresh();
        });
    }

    /** Prévenir la société au FRANCHISSEMENT du seuil, une seule fois. */
    protected function alerterSiFranchissement(InventoryItem $item, int $avant, int $apres): void
    {
        $seuil = (int) $item->reorder_threshold;

        if ($avant > $seuil && $apres <= $seuil) {
            try {
                // PRÉVENIR CEUX QUI PEUVENT AGIR, pas toute la société.
                $this->notifier->notifierPorteursDe(
                    organisationId: (int) $item->organization_account_id,
                    permission: 'inventory.manage',
                    titre: 'Stock bas : '.$item->name,
                    corps: sprintf('Il reste %d %s. Seuil de réappro : %d.', $apres, $item->unit, $seuil),
                    donnees: ['inventory_item_id' => $item->id],
                    cleIdempotence: 'inventory:reorder:'.$item->id.':'.$apres,
                );
            } catch (\Throwable $e) {
                // Une alerte qui échoue ne doit pas annuler le mouvement : le stock a bougé, c'est
                // le fait qui compte.
                report($e);
            }
        }
    }
}

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

/**
 * LE STOCK DE CONSOMMABLES D'UNE SOCIÉTÉ (E23), ET CE QU'ON Y PRÉLÈVE SUR PLACE (F7).
 *
 * Une société de nettoyage achète des produits, des sacs, des recharges, et les distribue à ses
 * équipes. Ce suivi se fait aujourd'hui sur un tableur — quand il se fait : personne ne sait ce qui
 * reste dans quelle agence, et on découvre la rupture le matin où une équipe part sans produit.
 *
 * TOUT PASSE PAR UN MOUVEMENT, jamais par une écriture directe du stock. Le compteur est le RÉSULTAT
 * des mouvements, pas une valeur qu'on ajuste : dès qu'on peut écrire le stock à la main, le
 * registre et le compteur divergent, et plus personne ne sait lequel croire. Corriger un écart est
 * légitime — mais c'est un mouvement `adjustment`, qui se déclare et se relit.
 *
 * LE STOCK NE DESCEND PAS SOUS ZÉRO. Une consommation supérieure au stock signale soit une erreur de
 * saisie, soit un stock déjà faux : dans les deux cas, l'accepter en silence produirait un compteur
 * négatif que personne ne saurait expliquer. On refuse et on le dit.
 *
 * L'ALERTE DE RÉAPPRO PART AU FRANCHISSEMENT, pas à chaque mouvement sous le seuil. Prévenir à
 * chaque prélèvement d'un article déjà bas transforme l'alerte en bruit, et le bruit se désactive.
 */
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
     * Le motif est OBLIGATOIRE ici, et seulement ici. Une réception et une consommation
     * s'expliquent d'elles-mêmes ; un ajustement, non — c'est précisément le mouvement qu'on
     * relira dans six mois en se demandant ce qui s'est passé.
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

    /**
     * Le cœur : un mouvement, puis le compteur qui en découle.
     *
     * VERROUILLÉ PENDANT LA TRANSACTION. Deux équipes qui prélèvent le même article au même moment
     * liraient sinon le même stock de départ, et la seconde écriture écraserait la première — un
     * carton disparaîtrait des comptes sans qu'aucune ligne ne manque.
     */
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

    /**
     * Prévenir la société au FRANCHISSEMENT du seuil, une seule fois.
     *
     * Alerter à chaque mouvement d'un article déjà bas transforme l'alerte en bruit, et le bruit se
     * désactive — après quoi la vraie rupture passe inaperçue.
     */
    protected function alerterSiFranchissement(InventoryItem $item, int $avant, int $apres): void
    {
        $seuil = (int) $item->reorder_threshold;

        if ($avant > $seuil && $apres <= $seuil) {
            try {
                /*
                 * PRÉVENIR CEUX QUI PEUVENT AGIR, pas toute la société. `inventory.manage` désigne
                 * exactement les personnes qui commandent : alerter les autres ferait du bruit
                 * chez des gens sans moyen d'y répondre.
                 *
                 * La clé d'idempotence porte le franchissement, pas l'article : un article qui
                 * repasse au-dessus du seuil puis redescend mérite une seconde alerte.
                 */
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

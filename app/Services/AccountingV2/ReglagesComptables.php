<?php

namespace App\Services\AccountingV2;

use App\Models\Parametre;
use Illuminate\Support\Facades\Schema;

/** LES RÉGLAGES QUE LE COMPTABLE DOIT POUVOIR CHANGER LUI-MÊME. */
class ReglagesComptables
{
    /** Le postage automatique des écritures est-il actif ? */
    public const POSTAGE_AUTOMATIQUE = 'comptabilite.postage_automatique';

    /** Taux de TVA des frais d'annulation. Vide = taux du pays ; « 0 » = hors champ. */
    public const TVA_FRAIS_ANNULATION = 'comptabilite.tva_frais_annulation';

    /** Modèle de revenu marketplace : `principal` ou `agent`. */
    public const MODELE_REVENU = 'comptabilite.modele_revenu';

    /** Le postage automatique est-il actif ? */
    public function postageAutomatique(): bool
    {
        $pose = $this->valeur(self::POSTAGE_AUTOMATIQUE);

        if ($pose === null) {
            return (bool) config('accounting_v2.auto_post_enabled', false);
        }

        return in_array(strtolower(trim($pose)), ['1', 'true', 'oui', 'on'], true);
    }

    /** Le taux de TVA des frais d'annulation, ou `null` pour « appliquer le taux du pays ». */
    public function tvaDesFraisDAnnulation(): ?float
    {
        $pose = $this->valeur(self::TVA_FRAIS_ANNULATION);

        if ($pose === null || trim($pose) === '') {
            $reglage = config('accounting_v2.marketplace.cancellation_fee_vat_rate');

            return $reglage === null || $reglage === '' ? null : (float) $reglage;
        }

        return (float) $pose;
    }

    /** `principal` : le TTC complet est en ventes. `agent` : seule la commission est un produit. */
    public function modeleDeRevenu(): string
    {
        $pose = $this->valeur(self::MODELE_REVENU);
        $valeur = $pose ?? (string) config('accounting_v2.marketplace.revenue_model', 'principal');

        return in_array($valeur, ['principal', 'agent'], true) ? $valeur : 'principal';
    }

    /** Enregistre une valeur, ou l'efface pour revenir au réglage de configuration. */
    public function poser(string $cle, int|float|string|bool|null $valeur): void
    {
        if (! $this->tableDisponible()) {
            return;
        }

        if ($valeur === null) {
            Parametre::query()->where('cle', $cle)->delete();

            return;
        }

        if (is_bool($valeur)) {
            $valeur = $valeur ? '1' : '0';
        }

        Parametre::setValeur($cle, (string) $valeur);
    }

    private function valeur(string $cle): ?string
    {
        if (! $this->tableDisponible()) {
            return null;
        }

        $brut = Parametre::query()->where('cle', $cle)->value('valeur');

        return $brut === null ? null : (string) $brut;
    }

    /** LE MODULE DOIT SURVIVRE À UNE BASE QUI N'A PAS ENCORE MIGRÉ. */
    private function tableDisponible(): bool
    {
        return Schema::hasTable('parametres');
    }
}

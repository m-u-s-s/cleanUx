<?php

namespace App\Services\AccountingV2;

use App\Models\Parametre;
use Illuminate\Support\Facades\Schema;

/**
 * LES RÉGLAGES QUE LE COMPTABLE DOIT POUVOIR CHANGER LUI-MÊME.
 *
 * Ils vivaient dans `config/accounting_v2.php`, donc dans des variables d'environnement. Changer
 * une position de TVA demandait un accès au serveur et un redéploiement — autant dire que le
 * comptable ne pouvait rien décider seul, alors que ce sont précisément SES décisions : elles
 * l'engagent, pas nous.
 *
 * ── LA BASE D'ABORD, LA CONFIGURATION EN REPLI ────────────────────────────────────────────────
 *
 * Cet ordre n'est pas un détail. La configuration reste la valeur de départ et la référence
 * documentée ; dès que le comptable pose une valeur, elle prime. Aucune migration de données, rien
 * à reprendre, et un déploiement sur une base vierge se comporte exactement comme avant.
 *
 * ── ZÉRO N'EST PAS UNE ABSENCE, ET C'EST LE PIÈGE DE CE FICHIER ───────────────────────────────
 *
 * « Frais d'annulation hors champ de la TVA » s'écrit `0`. Un `?:` ou un `empty()` le confondrait
 * avec « non renseigné » et ferait silencieusement retomber sur 21 %, c'est-à-dire déclarer une
 * TVA qu'on a décidé de ne pas devoir. Toutes les lectures ici testent donc `null`, jamais la
 * vacuité — le défaut que ce dépôt a déjà rencontré sur des tarifs à zéro voulus.
 *
 * ── POURQUOI PAS DE CACHE ─────────────────────────────────────────────────────────────────────
 *
 * Ces valeurs sont lues au passage d'écritures, pas dans une boucle chaude, et un cache mal
 * invalidé ferait écrire des mois de journal avec une position fiscale que le comptable croit
 * avoir changée. La lecture directe est le comportement sûr.
 */
class ReglagesComptables
{
    /** Le postage automatique des écritures est-il actif ? */
    public const POSTAGE_AUTOMATIQUE = 'comptabilite.postage_automatique';

    /** Taux de TVA des frais d'annulation. Vide = taux du pays ; « 0 » = hors champ. */
    public const TVA_FRAIS_ANNULATION = 'comptabilite.tva_frais_annulation';

    /** Modèle de revenu marketplace : `principal` ou `agent`. */
    public const MODELE_REVENU = 'comptabilite.modele_revenu';

    /**
     * Le postage automatique est-il actif ?
     *
     * Coupé par défaut, à dessein : la compta valide les écritures avant qu'elles ne s'accumulent.
     * Ce que ce fichier change, c'est QUI peut lever l'interrupteur.
     */
    public function postageAutomatique(): bool
    {
        $pose = $this->valeur(self::POSTAGE_AUTOMATIQUE);

        if ($pose === null) {
            return (bool) config('accounting_v2.auto_post_enabled', false);
        }

        return in_array(strtolower(trim($pose)), ['1', 'true', 'oui', 'on'], true);
    }

    /**
     * Le taux de TVA des frais d'annulation, ou `null` pour « appliquer le taux du pays ».
     *
     * `null` et `0.0` sont DEUX RÉPONSES DIFFÉRENTES : la première dit « traite-les comme un
     * produit ordinaire », la seconde « ces frais sont hors champ ». Les appelants doivent les
     * distinguer, d'où un type nullable plutôt qu'un `float` avec une valeur sentinelle.
     */
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

    /**
     * Enregistre une valeur, ou l'efface pour revenir au réglage de configuration.
     *
     * Effacer et poser une chaîne vide sont deux gestes distincts pour la TVA — le premier rend la
     * main à la configuration, le second dit « taux du pays ». On ne confond pas les deux ici : la
     * valeur `null` supprime la ligne.
     */
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

    /**
     * LE MODULE DOIT SURVIVRE À UNE BASE QUI N'A PAS ENCORE MIGRÉ.
     *
     * `BookingAutoPoster` s'appelle depuis des observateurs et des webhooks. Lever ici sur une
     * table absente ferait échouer un encaissement pour une raison comptable, ce qui est
     * exactement l'inverse de la règle du module : la comptabilité constate, elle ne pilote pas.
     */
    private function tableDisponible(): bool
    {
        return Schema::hasTable('parametres');
    }
}

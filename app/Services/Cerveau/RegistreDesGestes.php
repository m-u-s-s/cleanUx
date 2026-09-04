<?php

namespace App\Services\Cerveau;

use App\Models\CommissionRule;
use App\Models\MarketingCampaign;
use App\Models\PromoCode;
use App\Models\RiskHold;
use App\Models\User;
use App\Services\Commission\ResolveurDeCommission;
use App\Support\ActivityLogger;
use DomainException;

/**
 * TOUT CE QUE LE CERVEAU SAIT FAIRE — et rien d'autre.
 *
 * Un registre fermé, pas un interpréteur. Le cerveau ne compose pas d'action nouvelle : il
 * choisit dans cette liste. C'est ce qui rend son comportement prévisible, relisible, et
 * testable un geste à la fois.
 *
 * TROIS RÈGLES TIENNENT CE REGISTRE :
 *
 * 1. AUCUN GESTE NE SORT D'ARGENT. Pas de remboursement, pas de virement, pas de capture. Une
 *    automatisation qui déplace de l'argent finit par le déplacer une fois de trop, et un
 *    remboursement rendu à tort ne se reprend pas.
 * 2. AUCUN GESTE NE S'APPLIQUE SEUL. Le super-administrateur clique, toujours.
 * 3. CHAQUE GESTE DIT S'IL EST RÉVERSIBLE, et l'irréversible se dit avant, pas après.
 */
class RegistreDesGestes
{
    public const SUSPENDRE_CAMPAGNE = 'campagne.suspendre';

    public const REPRENDRE_CAMPAGNE = 'campagne.reprendre';

    public const SUSPENDRE_CODE_PROMO = 'promo.suspendre';

    public const METTRE_EN_REVUE = 'fraude.mettre_en_revue';

    public const SUSPENDRE_LA_REGLE = 'commission.suspendre_regle';

    /** @return array<string, Geste> */
    public function tous(): array
    {
        return [
            self::SUSPENDRE_CAMPAGNE => new Geste(
                cle: self::SUSPENDRE_CAMPAGNE,
                domaine: 'marketing',
                libelle: 'Mettre la campagne en pause',
                fait: 'La campagne cesse d’envoyer. Les messages déjà partis restent partis.',
                implique: 'Les destinataires qui n’ont pas encore reçu leur message ne le recevront pas '
                    .'tant que la campagne est en pause. Une séquence en plusieurs temps s’interrompt '
                    .'au milieu — le destinataire garde le premier message sans le second.',
                reversible: true,
                executer: function (User $acteur, array $args): string {
                    $campagne = MarketingCampaign::findOrFail($args['id'] ?? 0);
                    $campagne->forceFill(['status' => MarketingCampaign::STATUS_PAUSED])->save();

                    return 'Campagne « '.$campagne->name.' » mise en pause.';
                },
            ),

            self::REPRENDRE_CAMPAGNE => new Geste(
                cle: self::REPRENDRE_CAMPAGNE,
                domaine: 'marketing',
                libelle: 'Reprendre la campagne',
                fait: 'La campagne recommence à envoyer là où elle s’était arrêtée.',
                implique: 'Les destinataires en attente reçoivent leur message, y compris ceux qui '
                    .'attendaient depuis la mise en pause : si la pause a duré, le message peut arriver '
                    .'hors de son contexte.',
                reversible: true,
                executer: function (User $acteur, array $args): string {
                    $campagne = MarketingCampaign::findOrFail($args['id'] ?? 0);
                    $campagne->forceFill(['status' => MarketingCampaign::STATUS_RUNNING])->save();

                    return 'Campagne « '.$campagne->name.' » reprise.';
                },
            ),

            self::SUSPENDRE_CODE_PROMO => new Geste(
                cle: self::SUSPENDRE_CODE_PROMO,
                domaine: 'marketing',
                libelle: 'Suspendre le code promo',
                fait: 'Le code cesse d’être accepté à la réservation.',
                implique: 'Les réservations qui l’ont DÉJÀ utilisé gardent leur remise : suspendre un '
                    .'code ne rouvre aucune facture. En revanche, un client qui avait le code et s’apprêtait '
                    .'à s’en servir se verra refuser sans explication — prévoyez le message.',
                reversible: true,
                executer: function (User $acteur, array $args): string {
                    $code = PromoCode::findOrFail($args['id'] ?? 0);
                    $code->forceFill(['status' => PromoCode::STATUS_PAUSED])->save();

                    return 'Code promo « '.$code->code.' » suspendu.';
                },
            ),

            self::METTRE_EN_REVUE => new Geste(
                cle: self::METTRE_EN_REVUE,
                domaine: 'fraude',
                libelle: 'Mettre le compte en revue',
                fait: 'Le compte est marqué pour examen manuel. Il continue d’utiliser la plateforme.',
                implique: 'CE N’EST PAS UNE SUSPENSION, et c’est délibéré : un compte bloqué à tort est '
                    .'un client perdu et un litige. La revue met le dossier sur une pile que quelqu’un '
                    .'doit ouvrir — si personne ne l’ouvre, rien ne se passe.',
                reversible: true,
                executer: function (User $acteur, array $args): string {
                    $compte = User::findOrFail($args['user_id'] ?? 0);

                    RiskHold::create([
                        'user_id' => $compte->id,
                        'reason' => $args['motif'] ?? 'Signalé par l’analyse de la plateforme.',
                        'status' => RiskHold::STATUS_ACTIVE,
                        'expires_at' => now()->addDays(7),
                    ]);

                    return 'Compte '.$compte->email.' mis en revue.';
                },
            ),

            self::SUSPENDRE_LA_REGLE => new Geste(
                cle: self::SUSPENDRE_LA_REGLE,
                domaine: 'commission',
                libelle: 'Suspendre la règle de commission',
                fait: 'La règle cesse de s’appliquer ; le taux retombe sur la règle suivante, ou sur le taux d’origine.',
                implique: 'Les missions déjà conclues gardent le taux qu’elles ont payé — suspendre ne '
                    .'rouvre aucune facture. Le prochain devis, lui, changera de prix : vérifiez d’abord '
                    .'dans le simulateur ce qui prendra le relais.',
                reversible: true,
                executer: function (User $acteur, array $args): string {
                    $regle = CommissionRule::findOrFail($args['id'] ?? 0);
                    $regle->forceFill(['is_active' => false])->save();
                    app(ResolveurDeCommission::class)->oublierLeCache();

                    return 'Règle « '.$regle->label.' » suspendue.';
                },
            ),
        ];
    }

    public function trouver(string $cle): ?Geste
    {
        return $this->tous()[$cle] ?? null;
    }

    /**
     * APPLIQUER — le seul chemin, et il passe par le titulaire du siège.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws DomainException
     */
    public function appliquer(User $acteur, string $cle, array $arguments = []): string
    {
        if (! $acteur->isSuperAdmin()) {
            throw new DomainException('Seul le titulaire du siège applique un geste du cerveau.');
        }

        $geste = $this->trouver($cle);

        if ($geste === null) {
            // UN GESTE INCONNU NE S'INVENTE PAS. Le registre est fermé : c'est ce qui rend le
            // comportement du cerveau prévisible.
            throw new DomainException('Geste inconnu : '.$cle);
        }

        $compteRendu = ($geste->executer)($acteur, $arguments);

        ActivityLogger::critical('cerveau.geste_applique', $acteur, [
            'domain' => 'security',
            'geste' => $cle,
            'arguments' => $arguments,
            'compte_rendu' => $compteRendu,
        ]);

        return $compteRendu;
    }
}

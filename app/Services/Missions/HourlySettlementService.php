<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionTimeSettlement;
use App\Services\OrderEngine\HourlyRateResolver;
use App\Services\Payments\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;

/**
 * RÉGLER LE TEMPS SUPPLÉMENTAIRE — après l'intervention, jamais avant.
 *
 * POURQUOI APRÈS. Autoriser d'avance le pire des cas immobiliserait l'argent du client sur une
 * hypothèse : trois heures de ménage à 175 € demanderaient une empreinte de 310 € pour couvrir un
 * dépassement plafonné qui, neuf fois sur dix, n'arrivera pas. La page publique promet « paiement
 * après prestation » ; une empreinte au double du devis la démentirait sur le relevé bancaire.
 *
 * CE QU'ON NE TOUCHE JAMAIS : `devis_estime` et `payment_amount_cents`. Ce sont l'engagement et
 * l'autorisation, et la commission du prestataire se calcule dessus. Les gonfler après coup
 * créditerait au prestataire une part d'un argent que personne n'a encaissé — un défaut qui ne se
 * voit qu'au moment du versement, des semaines plus tard.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 *
 * QUI TOUCHE LA MAJORATION DE 30 % — une décision de produit, énoncée ici parce qu'elle ne se
 * devine pas.
 *
 * Le prestataire est payé à son tarif NORMAL sur tout le temps supplémentaire : il a réellement
 * travaillé ces minutes-là. La majoration, elle, revient à la plateforme.
 *
 * L'alternative — lui verser sa part de l'intégralité du montant majoré — le paierait 30 % de plus
 * de l'heure dès qu'il déborde. Or c'est lui qui décide du rythme sur place. On créerait une raison
 * de travailler lentement, et la règle censée protéger le client financerait son contraire.
 *
 * Cette répartition tient en une ligne, `partPrestataireCents()`, si elle doit être revue.
 */
class HourlySettlementService
{
    public function __construct(
        private readonly HourlyMissionClock $horloge,
        private readonly HourlyRateResolver $rates,
        private readonly CommissionService $commissions,
    ) {}

    /**
     * Constate ce qui reste dû sur une mission terminée, puis tente de l'encaisser.
     *
     * IDEMPOTENT : rappelé sur une mission déjà réglée, il ne recalcule rien et ne débite rien.
     * C'est indispensable — la clôture peut être rejouée, et une reprise planifiée passe derrière.
     */
    public function regler(Mission $mission): ?MissionTimeSettlement
    {
        $reglement = $this->constater($mission);

        if ($reglement === null || ! $reglement->estUneCreance()) {
            return $reglement;
        }

        $this->encaisser($reglement);

        return $reglement->refresh();
    }

    /**
     * LE CONSTAT — le calcul figé, sans le moindre appel à Stripe.
     *
     * Séparé de l'encaissement à dessein : le constat doit exister même si le réseau tombe, sinon
     * une panne de paiement effacerait aussi la trace de ce qui était dû.
     */
    public function constater(Mission $mission): ?MissionTimeSettlement
    {
        $booking = $mission->booking;

        if ($booking === null) {
            return null;
        }

        $existant = MissionTimeSettlement::query()->where('mission_id', $mission->getKey())->first();

        // Déjà encaissé : on ne recalcule pas un règlement clos. Le rejouer produirait un second
        // débit sur un dépassement qui a déjà été payé.
        if ($existant !== null && $existant->status === MissionTimeSettlement::STATUT_ENCAISSE) {
            return $existant;
        }

        $etat = $this->horloge->etat($mission);

        if (($etat['applies'] ?? false) !== true) {
            return null;
        }

        $tarif = (int) ($etat['effective_hourly_rate_cents'] ?? 0);
        $autoriseCents = $this->rates->montantFactureCents($booking);
        $autoriseMinutes = (int) ($booking->duree_estimee ?? 0);
        $acheteesMinutes = (int) ($etat['purchased_minutes'] ?? 0);
        $depassement = (int) ($etat['billable_overtime_minutes'] ?? 0);

        /*
         * LES MINUTES DE PROLONGATION SE DÉDUISENT, elles ne se comptent pas.
         *
         * `duree_estimee` est la durée VENDUE au moment de l'autorisation et la base du tarif ;
         * `purchased_minutes` est ce qui est dû aujourd'hui. Leur écart EST la prolongation. Tenir
         * un compteur séparé en ferait une troisième source, qui divergerait le jour où une
         * prolongation serait annulée sans qu'on pense à le décrémenter.
         */
        $prolongation = max(0, $acheteesMinutes - $autoriseMinutes);

        $montantProlongation = $this->auTarif($tarif, $prolongation, 1.0);
        $montantDepassement = (int) ($etat['overtime_amount_cents'] ?? 0);

        /*
         * CE QUI RESTE DÛ = CE QUI EST DÛ − CE QUI EST AUTORISÉ.
         *
         * Et non « prolongation + dépassement » calculés à part : cette forme-ci reste juste même
         * si les deux durées divergeaient d'un arrondi, et elle ne peut structurellement pas
         * réclamer une seconde fois ce qui est déjà couvert par l'empreinte.
         */
        $du = $this->auTarif($tarif, $acheteesMinutes, 1.0) + $montantDepassement;
        $reste = max(0, $du - $autoriseCents);

        $reglement = $existant ?? new MissionTimeSettlement([
            'mission_id' => $mission->getKey(),
            'booking_id' => $booking->getKey(),
        ]);

        $reglement->forceFill([
            'mission_id' => $mission->getKey(),
            'booking_id' => $booking->getKey(),
            'authorized_minutes' => $autoriseMinutes,
            'purchased_minutes' => $acheteesMinutes,
            'elapsed_minutes' => (int) ($etat['elapsed_minutes'] ?? 0),
            'extension_minutes' => $prolongation,
            'overtime_minutes' => $depassement,
            'grace_minutes' => (int) ($etat['grace_minutes'] ?? 0),
            'capped' => (bool) ($etat['capped'] ?? false),
            'effective_hourly_rate_cents' => $tarif > 0 ? $tarif : null,
            'overtime_multiplier' => (float) ($etat['overtime_multiplier'] ?? 1.0),
            'authorized_amount_cents' => $autoriseCents,
            'extension_amount_cents' => $montantProlongation,
            'overtime_amount_cents' => $montantDepassement,
            'amount_due_cents' => $reste,
            'currency' => strtoupper((string) ($booking->currency ?: 'EUR')),
            'status' => $reste > 0
                ? MissionTimeSettlement::STATUT_EN_ATTENTE
                : MissionTimeSettlement::STATUT_SANS_OBJET,
        ])->save();

        return $reglement;
    }

    /**
     * L'ENCAISSEMENT HORS SESSION — le même mécanisme que les suppléments acceptés.
     *
     * `charged` N'EST ÉCRIT QUE SI STRIPE L'A DIT. Une carte peut réclamer une authentification
     * forte hors session : l'intention part alors en `requires_action` et rien n'est débité.
     * L'écrire encaissé refabriquerait exactement le défaut réparé sur les suppléments — une
     * créance déclarée payée que plus rien ne rattrape.
     */
    public function encaisser(MissionTimeSettlement $reglement): void
    {
        if (! $reglement->estUneCreance()) {
            return;
        }

        $booking = $reglement->booking;
        $mission = $reglement->mission;

        if ($booking === null || $mission === null) {
            $this->noterLEchec($reglement, 'réservation ou mission introuvable');

            return;
        }

        // Le même prestataire que la commission : `employe_id` est la colonne historique, une
        // réservation moderne porte `assigned_provider_user_id` et laisse la première vide.
        $prestataire = $booking->assignedProvider ?? $booking->employe;

        if (! $prestataire?->canReceiveStripeConnectPayments() || ! $booking->client?->stripe_id) {
            $this->noterLEchec($reglement, 'compte Connect ou client Stripe indisponible');

            return;
        }

        $carte = $this->carteDuClient($booking);

        if ($carte === null) {
            $this->noterLEchec($reglement, 'aucun moyen de paiement réutilisable sur la réservation');

            return;
        }

        try {
            $partPrestataire = $this->partPrestataireCents($reglement);
            $commission = $this->commissions->calculateForAmount($partPrestataire, $prestataire);

            /*
             * LA COMMISSION SE CALCULE SUR LA PART DU PRESTATAIRE, puis la MAJORATION VIENT S'Y
             * AJOUTER : `application_fee_amount` est donc « la commission normale + les 30 % ».
             *
             * C'est la traduction en centimes de la décision énoncée en tête de fichier — le
             * prestataire est payé à son tarif normal sur le temps supplémentaire, la plateforme
             * garde la majoration. La calculer sur le montant total lui verserait 30 % de plus de
             * l'heure dès qu'il déborde.
             */
            $fraisPlateforme = $reglement->amount_due_cents
                - ($partPrestataire - $commission['platform_fee_cents']);

            $intent = PaymentIntent::create([
                'amount' => $reglement->amount_due_cents,
                'currency' => strtolower($reglement->currency),
                'customer' => $booking->client->stripe_id,
                'payment_method' => $carte,
                // Le client n'est plus devant son écran, l'intervention est finie et la règle a été
                // annoncée avant la commande. C'est le cas que Stripe nomme « off-session ».
                'confirm' => true,
                'off_session' => true,
                'application_fee_amount' => max(0, $fraisPlateforme),
                'transfer_data' => ['destination' => (string) $prestataire->stripe_connect_account_id],
                'metadata' => [
                    'mission_time_settlement_id' => (string) $reglement->id,
                    'mission_id' => (string) $mission->id,
                    'booking_reference' => (string) $booking->booking_reference,
                    'extension_minutes' => (string) $reglement->extension_minutes,
                    'overtime_minutes' => (string) $reglement->overtime_minutes,
                ],
            ]);

            if (($intent->status ?? null) !== 'succeeded') {
                $reglement->forceFill(['stripe_payment_intent_id' => $intent->id])->save();
                $this->noterLEchec($reglement, 'intention non aboutie : '.($intent->status ?? 'statut inconnu'));

                return;
            }

            $reglement->forceFill([
                'status' => MissionTimeSettlement::STATUT_ENCAISSE,
                'charged_at' => now(),
                'stripe_payment_intent_id' => $intent->id,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $this->noterLEchec($reglement, $e->getMessage());
        }
    }

    /**
     * La part qui revient au prestataire, AVANT commission : le temps supplémentaire à son tarif
     * normal, majoration exclue.
     */
    public function partPrestataireCents(MissionTimeSettlement $reglement): int
    {
        $tarif = (int) ($reglement->effective_hourly_rate_cents ?? 0);
        $minutes = $reglement->extension_minutes + $reglement->overtime_minutes;

        // Jamais plus que ce qui est réellement encaissé : sur un arrondi défavorable, verser plus
        // que le débit ferait payer la différence à la plateforme sans qu'elle l'ait décidé.
        return min($reglement->amount_due_cents, $this->auTarif($tarif, $minutes, 1.0));
    }

    // ─────────────────────────────────────────────────────────────────────

    private function auTarif(int $tarifCents, int $minutes, float $multiplicateur): int
    {
        if ($tarifCents <= 0 || $minutes <= 0) {
            return 0;
        }

        return (int) round($tarifCents * ($minutes / 60) * $multiplicateur);
    }

    /**
     * LA CARTE DÉJÀ UTILISÉE POUR CETTE RÉSERVATION.
     *
     * On la relit sur l'intention d'origine plutôt que de prendre « une » carte du client : une
     * personne peut en avoir plusieurs, et débiter celle qu'elle n'a pas choisie pour cette
     * réservation est une réclamation garantie.
     */
    private function carteDuClient(Booking $booking): ?string
    {
        if (! $booking->stripe_payment_intent_id) {
            return null;
        }

        try {
            $origine = PaymentIntent::retrieve($booking->stripe_payment_intent_id);

            $methode = $origine->payment_method ?? null;

            return is_string($methode) ? $methode : ($methode->id ?? null);
        } catch (\Throwable $e) {
            Log::warning('Règlement du temps : moyen de paiement d’origine illisible', [
                'booking_id' => $booking->id,
                'raison' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * LA TRACE DE L'ÉCHEC — sans elle, un règlement `pending` depuis trois jours ne dit pas si le
     * prélèvement a seulement été tenté.
     */
    private function noterLEchec(MissionTimeSettlement $reglement, string $motif): void
    {
        DB::transaction(function () use ($reglement, $motif) {
            $reglement->forceFill([
                'status' => MissionTimeSettlement::STATUT_ECHOUE,
                'attempts' => $reglement->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => mb_substr($motif, 0, 1000),
            ])->save();
        });
    }
}

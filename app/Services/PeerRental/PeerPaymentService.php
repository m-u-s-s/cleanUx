<?php

namespace App\Services\PeerRental;

use App\Models\PeerRental;
use App\Models\User;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * L'ARGENT D'UNE LOCATION ENTRE MEMBRES.
 *
 * Le loyer est BLOQUE a la reservation et n'est CAPTURE qu'a la remise des cles, quand les
 * deux parties ont confirme. La caution est une SECONDE empreinte, posee a la remise et
 * gardee par la plateforme : elle ne porte pas de `transfer_data`, sans quoi l'argent d'une
 * garantie partirait chez le proprietaire avant tout constat.
 *
 * Le versement se fait par CHARGE A DESTINATION — `application_fee_amount` + `transfer_data`.
 * Executer un `Stripe\Payout` en plus paierait le proprietaire DEUX FOIS.
 */
class PeerPaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * LE LOYER, BLOQUE. Rien n'est encaisse : `capture_method` reste manuel.
     *
     * @throws RuntimeException si le proprietaire ne peut pas encore etre paye
     */
    public function autoriserLeLoyer(PeerRental $location, string $paymentMethodId): PaymentIntent
    {
        $location->loadMissing(['owner', 'renter']);

        $proprietaire = $location->owner;

        if (! $proprietaire || ! $proprietaire->canReceiveStripeConnectPayments()) {
            throw new RuntimeException('Le propriétaire ne peut pas encore recevoir de paiements Stripe Connect.');
        }

        $this->assurerUnClientStripe($location->renter);

        $intention = PaymentIntent::create([
            'amount' => $location->total_cents,
            'currency' => strtolower($location->currency ?: (string) config('fx.base_currency', 'EUR')),
            'customer' => $location->renter?->stripe_id,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'capture_method' => 'manual',
            'application_fee_amount' => $location->platform_fee_cents,
            'transfer_data' => ['destination' => $proprietaire->stripe_connect_account_id],
            'metadata' => $this->empreinte($location, 'loyer'),
        ]);

        $location->forceFill([
            'stripe_payment_intent_id' => $intention->id,
            'payment_status' => PeerRental::PAIEMENT_AUTORISE,
            'payment_authorized_at' => now(),
            'payment_authorized_until' => now()->addDays((int) config('peer_rental.authorization_days', 7)),
        ])->save();

        return $intention;
    }

    /**
     * LA MEME EMPREINTE, REPOSEE AVANT QU'ELLE NE TOMBE.
     *
     * Une autorisation Stripe expire au bout de sept jours. Une location reservee trois
     * semaines a l'avance verrait ses fonds retomber avant la remise, et « paiement bloque a
     * la reservation » deviendrait faux des la deuxieme semaine.
     */
    public function reautoriserLeLoyer(PeerRental $location): ?PaymentIntent
    {
        if ($location->payment_status !== PeerRental::PAIEMENT_AUTORISE) {
            return null;
        }

        $moyen = $this->moyenDePaiementDe($location);

        if ($moyen === null) {
            return null;
        }

        $ancienne = $location->stripe_payment_intent_id;

        $nouvelle = $this->autoriserLeLoyer($location, $moyen);

        // L'ANCIENNE SE LIBERE APRES, JAMAIS AVANT : liberer d'abord et echouer ensuite
        // laisserait la location sans aucune empreinte.
        if ($ancienne !== null && $ancienne !== $nouvelle->id) {
            $this->annulerSansBruit($ancienne);
        }

        $location->forceFill([
            'reauthorized_count' => $location->reauthorized_count + 1,
        ])->save();

        return $nouvelle;
    }

    /** LA CAPTURE — elle n'a lieu qu'a la remise des cles, et seulement alors. */
    public function capturerLeLoyer(PeerRental $location): ?PaymentIntent
    {
        if (! $location->stripe_payment_intent_id || $location->payment_status !== PeerRental::PAIEMENT_AUTORISE) {
            return null;
        }

        if (! $location->remiseConfirmeeParLesDeux()) {
            throw new RuntimeException('La remise des clés n’est pas confirmée par les deux parties.');
        }

        $intention = PaymentIntent::retrieve($location->stripe_payment_intent_id);
        $intention->capture();

        $location->forceFill([
            'payment_status' => PeerRental::PAIEMENT_CAPTURE,
            'payment_captured_at' => now(),
        ])->save();

        return $intention;
    }

    /**
     * LA CAUTION — une empreinte QUI RESTE SUR LA PLATEFORME.
     *
     * Pas de `transfer_data` : une garantie n'appartient a personne tant qu'aucun dommage
     * n'est constate. La verser au proprietaire d'avance reviendrait a arbitrer avant le retour.
     */
    public function autoriserLaCaution(PeerRental $location, ?string $paymentMethodId = null): ?PaymentIntent
    {
        if ($location->deposit_cents <= 0 || $location->deposit_payment_intent_id !== null) {
            return null;
        }

        $moyen = $paymentMethodId ?? $this->moyenDePaiementDe($location);

        if ($moyen === null) {
            throw new RuntimeException('Aucun moyen de paiement pour la caution.');
        }

        $this->assurerUnClientStripe($location->renter);

        $intention = PaymentIntent::create([
            'amount' => $location->deposit_cents,
            'currency' => strtolower($location->currency ?: (string) config('fx.base_currency', 'EUR')),
            'customer' => $location->renter?->stripe_id,
            'payment_method' => $moyen,
            'confirm' => true,
            'capture_method' => 'manual',
            'metadata' => $this->empreinte($location, 'caution'),
        ]);

        $location->forceFill([
            'deposit_payment_intent_id' => $intention->id,
            'deposit_authorized_at' => now(),
        ])->save();

        return $intention;
    }

    /** LE RETOUR EST CONFORME : la caution retombe, sans un centime preleve. */
    public function libererLaCaution(PeerRental $location): ?PaymentIntent
    {
        if (! $location->deposit_payment_intent_id || $location->deposit_released_at !== null) {
            return null;
        }

        $intention = PaymentIntent::retrieve($location->deposit_payment_intent_id);

        if (in_array($intention->status, ['requires_capture', 'requires_confirmation'], true)) {
            $intention->cancel();
        }

        $location->forceFill(['deposit_released_at' => now()])->save();

        return $intention;
    }

    /**
     * UNE RETENUE SUR LA CAUTION — jamais plus que l'empreinte, jamais zero.
     *
     * Stripe refuse une capture nulle : rien a retenir se traduit par une liberation, pas
     * par une capture de zero qui echouerait en silence.
     */
    public function retenirSurLaCaution(PeerRental $location, int $montantCents): ?PaymentIntent
    {
        if (! $location->deposit_payment_intent_id || $location->deposit_released_at !== null) {
            return null;
        }

        $intention = PaymentIntent::retrieve($location->deposit_payment_intent_id);
        $capturable = (int) ($intention->amount_capturable ?? $intention->amount ?? 0);
        $aPrendre = min(max(0, $montantCents), $capturable);

        if ($aPrendre <= 0) {
            return $this->libererLaCaution($location);
        }

        $intention->capture(['amount_to_capture' => $aPrendre]);

        $location->forceFill([
            'deposit_captured_cents' => $aPrendre,
            'deposit_released_at' => now(),
        ])->save();

        return $intention;
    }

    /**
     * L'ANNULATION — on libere, ou on capture les frais dus, jamais les deux.
     *
     * @param  int  $fraisCents  ce que le bareme retient au profit du proprietaire
     */
    public function solderALAnnulation(PeerRental $location, int $fraisCents): ?PaymentIntent
    {
        if (! $location->stripe_payment_intent_id) {
            return null;
        }

        if ($location->payment_status !== PeerRental::PAIEMENT_AUTORISE) {
            return null;
        }

        $intention = PaymentIntent::retrieve($location->stripe_payment_intent_id);
        $capturable = (int) ($intention->amount_capturable ?? $intention->amount ?? 0);
        $aPrendre = min(max(0, $fraisCents), $capturable);

        if ($aPrendre <= 0) {
            $intention->cancel();

            $location->forceFill([
                'payment_status' => PeerRental::PAIEMENT_REMBOURSE,
                'metadata' => $this->journal($location, 'annulation', ['dus_cents' => $fraisCents, 'pris_cents' => 0]),
            ])->save();

            return $intention;
        }

        $intention->capture(['amount_to_capture' => $aPrendre]);

        $location->forceFill([
            'payment_status' => PeerRental::PAIEMENT_CAPTURE,
            'payment_captured_at' => now(),
            'metadata' => $this->journal($location, 'annulation', ['dus_cents' => $fraisCents, 'pris_cents' => $aPrendre]),
        ])->save();

        return $intention;
    }

    /** L'empreinte est tombee sans avoir ete capturee : la location ne tient plus. */
    public function marquerExpire(PeerRental $location): void
    {
        $location->forceFill(['payment_status' => PeerRental::PAIEMENT_EXPIRE])->save();
    }

    public function marquerEchoue(PeerRental $location): void
    {
        $location->forceFill(['payment_status' => PeerRental::PAIEMENT_ECHOUE])->save();
    }

    private function assurerUnClientStripe(?User $utilisateur): void
    {
        if ($utilisateur === null) {
            throw new RuntimeException('La location n’a pas de locataire.');
        }

        if (! $utilisateur->stripe_id) {
            $utilisateur->createAsStripeCustomer();
            $utilisateur->refresh();
        }
    }

    /** Le moyen de paiement deja employe pour cette location, relu chez Stripe. */
    private function moyenDePaiementDe(PeerRental $location): ?string
    {
        if (! $location->stripe_payment_intent_id) {
            return null;
        }

        $intention = PaymentIntent::retrieve($location->stripe_payment_intent_id);
        $moyen = $intention->payment_method ?? null;

        return is_string($moyen) ? $moyen : null;
    }

    private function annulerSansBruit(string $intentId): void
    {
        try {
            $intention = PaymentIntent::retrieve($intentId);

            if (in_array($intention->status, ['requires_capture', 'requires_confirmation'], true)) {
                $intention->cancel();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, int|string> */
    private function empreinte(PeerRental $location, string $nature): array
    {
        return [
            'peer_rental_id' => $location->id,
            'peer_rental_reference' => $location->reference,
            'nature' => $nature,
            'owner_id' => (int) $location->owner_id,
            'renter_id' => (int) $location->renter_id,
            'platform_fee_cents' => (int) $location->platform_fee_cents,
            'owner_payout_cents' => (int) $location->owner_payout_cents,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function journal(PeerRental $location, string $cle, array $donnees): array
    {
        $journal = $location->metadata ?? [];
        $journal[$cle] = $donnees + ['a' => now()->toIso8601String()];

        return $journal;
    }
}

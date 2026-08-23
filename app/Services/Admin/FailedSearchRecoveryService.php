<?php

namespace App\Services\Admin;

use App\Models\AsapDispatchRequest;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Organizations\OrganizationNotifier;
use App\Support\Domain\AsapStatus;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** RATTRAPER UNE RECHERCHE ÉCHOUÉE (E31). CE QUI SE PASSE AUJOURD'HUI. */
class FailedSearchRecoveryService
{
    public function __construct(
        protected DispatchEngine $dispatch,
        protected OrganizationNotifier $notifier,
    ) {}

    /**
     * Relancer la recherche.
     *
     * @throws DomainException
     */
    public function relancer(AsapDispatchRequest $recherche, User $admin): AsapDispatchRequest
    {
        if ($recherche->status !== AsapStatus::EXPIRED) {
            // Le moteur ouvrirait une SECONDE recherche sur la même réservation, et deux prestataires se déplaceraient — le défaut exact que la porte amont unique a corrigé.
            throw new DomainException('Cette recherche n’est pas épuisée : elle suit encore son cours.');
        }

        $booking = $recherche->booking;

        if ($booking === null) {
            throw new DomainException('La réservation de cette recherche n’existe plus.');
        }

        $this->dispatch->dispatchBooking($booking);

        $recherche->forceFill([
            'metadata' => array_merge((array) $recherche->metadata, [
                // La trace de la relance : sans elle, on ne saurait pas qu'une recherche a été
                // rejouée, ni combien de fois.
                'relaunched_by' => $admin->id,
                'relaunched_at' => now()->toIso8601String(),
            ]),
        ])->save();

        Log::info('[recovery] recherche relancée', [
            'search_id' => $recherche->id,
            'booking_id' => $booking->id,
            'admin_id' => $admin->id,
        ]);

        return $recherche->fresh();
    }

    /**
     * Prévenir le client qu'on s'occupe de lui. PAS UN COURRIEL AUTOMATIQUE DE PLUS.
     *
     * @throws DomainException
     */
    public function contacterLeClient(AsapDispatchRequest $recherche, User $admin, string $message): void
    {
        $clientId = $recherche->booking?->client_id;

        if ($clientId === null) {
            throw new DomainException('Cette recherche n’a pas de client identifiable.');
        }

        $this->notifier->notifierUtilisateur(
            (int) $clientId,
            'Votre demande d’intervention',
            $message,
            ['booking_id' => $recherche->booking_id],
            'recovery:contact:'.$recherche->id,
        );

        $recherche->forceFill([
            'metadata' => array_merge((array) $recherche->metadata, [
                'contacted_by' => $admin->id,
                'contacted_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    /**
     * Offrir un geste commercial — un code NOMINATIF.
     *
     * @throws DomainException
     */
    public function offrirUnGeste(
        AsapDispatchRequest $recherche,
        User $admin,
        int $pourcentage = 15,
        int $validiteJours = 30,
    ): PromoCode {
        $client = $recherche->booking?->clientUser;

        if ($client === null) {
            throw new DomainException('Cette recherche n’a pas de client identifiable.');
        }

        if ($pourcentage < 1 || $pourcentage > 50) {
            // Au-delà de la moitié, ce n'est plus un geste : c'est une décision commerciale qui se
            // prend ailleurs qu'en un clic depuis un écran de rattrapage.
            throw new DomainException('Un geste commercial se situe entre 1 et 50 %.');
        }

        /** @var PromoCode $code */
        $code = PromoCode::query()->create([
            // NOMINATIF, ET C'EST TOUT L'ENJEU.
            'code' => 'DESOLE-'.Str::upper(Str::random(6)),
            'name' => 'Geste commercial — recherche sans réponse',
            'description' => sprintf('Émis par %s après une recherche épuisée.', $admin->name ?? 'l’équipe'),
            // `percent` ET NON `percentage` : la colonne porte une contrainte d'énumération, et la constante du modèle fait foi.
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => $pourcentage,
            // `issued_to_user_id` ET NON `user_id` : c'est la colonne que le module de promotions lit réellement pour restreindre un code à son destinataire.
            'issued_to_user_id' => $client->id,
            'max_total_uses' => 1,
            'max_uses_per_user' => 1,
            'valid_from' => Carbon::now(),
            'valid_until' => Carbon::now()->addDays($validiteJours),
            'status' => PromoCode::STATUS_ACTIVE,
            'source' => PromoCode::SOURCE_SYSTEM,
            'created_by_user_id' => $admin->id,
        ]);

        try {
            $this->notifier->notifierUtilisateur(
                (int) $client->id,
                'Un geste de notre part',
                sprintf(
                    'Nous n’avons pas trouvé de professionnel pour votre demande. Voici %d %% sur votre prochaine intervention : %s',
                    $pourcentage,
                    $code->code,
                ),
                ['promo_code' => $code->code],
                'recovery:gesture:'.$recherche->id,
            );
        } catch (\Throwable $e) {
            // Le code existe : une notification qui échoue ne doit pas l'annuler, et il reste
            // lisible depuis l'écran du client.
            report($e);
        }

        $recherche->forceFill([
            'metadata' => array_merge((array) $recherche->metadata, [
                'gesture_code' => $code->code,
                'gesture_by' => $admin->id,
                'gesture_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $code;
    }
}

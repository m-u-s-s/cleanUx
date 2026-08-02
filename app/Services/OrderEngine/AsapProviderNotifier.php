<?php

namespace App\Services\OrderEngine;

use App\Models\AsapDispatchNotification;
use App\Models\AsapDispatchRequest;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Prévenir les prestataires du rayon — pour de vrai.
 *
 * Le compteur de l'écran client disait combien de personnes étaient joignables ; personne n'était
 * réellement prévenu. Une recherche qui n'atteint personne n'aboutit jamais, et le client attend
 * jusqu'à l'expiration sans qu'aucun professionnel n'ait su qu'on avait besoin de lui.
 *
 * CHACUN N'EST PRÉVENU QU'UNE FOIS par recherche. Élargir le rayon ne renotifie que les NOUVEAUX :
 * trois élargissements et le prestataire le plus proche recevrait quatre fois la même course. C'est
 * ainsi qu'on se fait couper les notifications, et une fois coupées elles ne reviennent pas. La
 * garantie est l'index unique (recherche, prestataire) en base — le filtre en amont ne fait
 * qu'épargner du travail, il ne protège rien à lui seul.
 *
 * UN ENVOI QUI ÉCHOUE N'ARRÊTE PAS LA RECHERCHE. Un jeton mort chez un prestataire ne doit pas
 * priver les onze autres de la course. L'échec est ÉCRIT plutôt qu'avalé : une recherche sans
 * réponse alors que tous les envois ont échoué est un incident, pas un manque de prestataires.
 */
class AsapProviderNotifier
{
    public function __construct(
        protected ProviderAvailabilityLookup $lookup,
        protected PushService $push,
    ) {}

    /**
     * Prévient les prestataires du rayon qui ne l'ont pas encore été.
     *
     * @return int le nombre de nouveaux prestataires prévenus lors de cet appel
     */
    public function notify(AsapDispatchRequest $request): int
    {
        $trade = $request->trade;

        if (! $trade || $request->lat === null || $request->lng === null) {
            return 0;
        }

        $nearby = $this->lookup->nearby($trade, $request->lat, $request->lng, $request->radius_m);

        if ($nearby->isEmpty()) {
            return 0;
        }

        /*
         * Ceux qu'on a déjà touchés pour CETTE recherche, à n'importe quel palier de rayon.
         *
         * Ce filtre évite un travail inutile ; ce n'est PAS lui qui garantit l'unicité. La
         * garantie est l'index unique (recherche, prestataire) : le retirer ne change rien au
         * résultat, l'écriture est simplement refusée par la base un cran plus loin. Écrit ici
         * pour que personne ne croie pouvoir supprimer l'index en gardant le filtre.
         */
        $alreadyNotified = $request->notifications()->pluck('user_id')->all();
        $fresh = $nearby->reject(fn (array $p) => in_array($p['id'], $alreadyNotified, true));

        if ($fresh->isEmpty()) {
            return 0;
        }

        $providers = User::query()->whereIn('id', $fresh->pluck('id'))->get()->keyBy('id');
        $sent = 0;

        foreach ($fresh as $candidate) {
            $provider = $providers->get($candidate['id']);

            if (! $provider) {
                continue;
            }

            if ($this->notifyOne($request, $provider, (int) $candidate['distance_m'])) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Un prestataire, une proposition.
     *
     * L'inscription au registre passe AVANT l'envoi. Si l'envoi échoue, la trace existe quand même
     * et porte l'erreur ; si on écrivait après, un plantage laisserait un prestataire prévenu sans
     * trace, donc renotifié au prochain élargissement.
     */
    protected function notifyOne(AsapDispatchRequest $request, User $provider, int $distanceM): bool
    {
        try {
            $notification = AsapDispatchNotification::create([
                'asap_dispatch_request_id' => $request->id,
                'user_id' => $provider->id,
                'distance_m' => $distanceM,
                'radius_m' => $request->radius_m,
                'notified_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Course entre deux appels simultanés : l'index unique a tranché, et c'est le
            // comportement voulu — on ne prévient pas deux fois.
            return false;
        }

        try {
            $this->push->dispatchToUser(
                $provider,
                'Nouvelle course',
                $this->body($request, $distanceM),
                [
                    'type' => 'asap_dispatch',
                    'asap_dispatch_request_id' => $request->id,
                    'trade' => $request->trade?->name,
                    'distance_m' => $distanceM,
                ],
                PushNotification::CATEGORY_TRANSACTIONAL,
                // Idempotence côté push aussi : un rejeu ne fait pas vibrer deux fois le téléphone.
                'asap:'.$request->id.':'.$provider->id,
                $request,
            );
        } catch (\Throwable $e) {
            /*
             * L'échec est écrit, pas avalé. La recherche continue pour les autres : un jeton mort
             * chez l'un ne doit pas priver les onze autres de la course.
             */
            $notification->update(['delivery_error' => mb_substr($e->getMessage(), 0, 250)]);

            Log::warning('AsapProviderNotifier: envoi impossible', [
                'request_id' => $request->id,
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /** Ce que le prestataire lit sur son écran verrouillé : le métier, et à quelle distance. */
    protected function body(AsapDispatchRequest $request, int $distanceM): string
    {
        return sprintf(
            '%s à %s km de vous. Répondez vite : la course part au premier qui accepte.',
            $request->trade->name,
            number_format($distanceM / 1000, 1, ',', ' '),
        );
    }
}

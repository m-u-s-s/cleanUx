<?php

namespace App\Services\EmailV2\Webhooks;

use App\Models\EmailMessage;
use App\Models\EmailWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CE QUE LE SERVICE D'EXPÉDITION NOUS APPREND, ET CE QU'ON EN FAIT.
 *
 * Trois règles gouvernent l'écriture, et chacune répare une erreur classique :
 *
 *   1. L'ÉVÉNEMENT S'ENREGISTRE UNE SEULE FOIS. `provider_event_id` est unique en base : un
 *      fournisseur rejoue ses appels, et un double comptage fausserait la mesure pour toujours.
 *   2. LE STATUT NE RECULE PAS. Un « remis » qui arrive après un « ouvert » — l'ordre n'est jamais
 *      garanti sur un réseau — ne doit pas effacer l'ouverture.
 *   3. L'HORODATAGE D'UN ÉVÉNEMENT NE S'ÉCRASE PAS. Le premier « ouvert » est la donnée utile ;
 *      les suivants disent seulement que la personne a relu.
 */
class ReceptionDesEvenementsEmail
{
    /**
     * L'ORDRE DE PROGRESSION D'UN ENVOI.
     *
     * Un rebond ou une plainte est terminal : ils gagnent sur tout le reste, parce qu'ils disent
     * que l'adresse pose problème — l'information la plus coûteuse à perdre.
     */
    private const RANG = [
        'pending' => 0, 'queued' => 1, 'sent' => 2, 'delivered' => 3,
        'opened' => 4, 'clicked' => 5, 'bounced' => 9, 'complained' => 9, 'failed' => 9,
    ];

    /** La colonne d'horodatage que chaque événement renseigne. */
    private const HORODATAGE = [
        'delivered' => 'delivered_at',
        'opened' => 'opened_at',
        'clicked' => 'clicked_at',
        'bounced' => 'bounced_at',
        'complained' => 'complained_at',
    ];

    /**
     * @param  list<array<string, mixed>>  $evenements
     * @return array{enregistres: int, ignores: int}
     */
    public function recevoir(string $fournisseur, array $evenements): array
    {
        $enregistres = 0;
        $ignores = 0;

        foreach ($evenements as $evenement) {
            $identifiant = (string) ($evenement['provider_event_id'] ?? '');
            $type = (string) ($evenement['event_type'] ?? '');

            // SANS IDENTIFIANT, PAS D'IDEMPOTENCE POSSIBLE : on refuse plutôt que de compter deux fois.
            if ($identifiant === '' || ! isset(self::HORODATAGE[$type])) {
                $ignores++;

                continue;
            }

            if (EmailWebhookEvent::query()->where('provider_event_id', $identifiant)->exists()) {
                $ignores++;

                continue;
            }

            $message = $this->messageVise($evenement);

            DB::transaction(function () use ($fournisseur, $evenement, $identifiant, $type, $message) {
                EmailWebhookEvent::query()->create([
                    'provider' => $fournisseur,
                    'provider_event_id' => $identifiant,
                    'provider_message_id' => $evenement['provider_message_id'] ?? null,
                    'email_message_id' => $message?->id,
                    'event_type' => $type,
                    'occurred_at' => $evenement['occurred_at'] ?? Carbon::now(),
                    'payload' => $evenement['payload'] ?? [],
                ]);

                if ($message instanceof EmailMessage) {
                    $this->appliquer($message, $type, $evenement['occurred_at'] ?? null);
                }
            });

            $enregistres++;
        }

        return ['enregistres' => $enregistres, 'ignores' => $ignores];
    }

    /**
     * L'ÉVÉNEMENT S'APPLIQUE À L'ENVOI, SANS JAMAIS LE FAIRE RECULER.
     */
    private function appliquer(EmailMessage $message, string $type, ?string $quand): void
    {
        $colonne = self::HORODATAGE[$type];
        $changements = [];

        // Le PREMIER horodatage fait foi : les suivants disent seulement qu'on a relu.
        if ($message->{$colonne} === null) {
            $changements[$colonne] = $quand !== null ? Carbon::parse($quand) : Carbon::now();
        }

        $rangActuel = self::RANG[(string) $message->status] ?? 0;
        $rangNouveau = self::RANG[$type] ?? 0;

        if ($rangNouveau > $rangActuel) {
            $changements['status'] = $type;
        }

        if ($changements !== []) {
            $message->forceFill($changements)->save();
        }
    }

    /** @param array<string, mixed> $evenement */
    private function messageVise(array $evenement): ?EmailMessage
    {
        $identifiant = (string) ($evenement['provider_message_id'] ?? '');

        if ($identifiant === '') {
            return null;
        }

        return EmailMessage::query()->where('provider_message_id', $identifiant)->first();
    }
}

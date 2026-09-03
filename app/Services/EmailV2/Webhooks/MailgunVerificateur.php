<?php

namespace App\Services\EmailV2\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * LA SIGNATURE MAILGUN, ET SA FENÊTRE.
 *
 * Mailgun signe `timestamp + token` en HMAC-SHA256 avec la clé de signature du compte. Deux
 * contrôles, pas un :
 *
 *   — la SIGNATURE, comparée en temps constant : `===` sur des chaînes fuit leur longueur commune
 *     et permet, à force d'essais, de reconstruire la valeur attendue octet par octet ;
 *   — l'HORODATAGE, borné à quinze minutes. Sans lui, une requête authentique captée une fois se
 *     rejoue indéfiniment : la signature reste valable pour toujours.
 */
class MailgunVerificateur implements VerificateurDeSignature
{
    /** Au-delà, une requête authentique est considérée comme rejouée. */
    private const FENETRE_SECONDES = 900;

    public function fournisseur(): string
    {
        return 'mailgun';
    }

    public function verifie(Request $requete): bool
    {
        $cle = (string) config('email_v2.webhooks.mailgun.signing_key', '');

        // PAS DE CLÉ, PAS D'ENTRÉE. Un secret absent ne vaut pas « accepter tout le monde ».
        if ($cle === '') {
            return false;
        }

        $signature = (array) $requete->input('signature', []);
        $horodatage = (string) ($signature['timestamp'] ?? '');
        $jeton = (string) ($signature['token'] ?? '');
        $empreinte = (string) ($signature['signature'] ?? '');

        if ($horodatage === '' || $jeton === '' || $empreinte === '') {
            return false;
        }

        if (abs(Carbon::now()->getTimestamp() - (int) $horodatage) > self::FENETRE_SECONDES) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $horodatage.$jeton, $cle), $empreinte);
    }

    public function evenements(Request $requete): array
    {
        $donnees = (array) $requete->input('event-data', []);
        $type = $this->typeNormalise((string) ($donnees['event' ?? ''] ?? ''), $donnees);

        if ($type === null) {
            return [];
        }

        $horodatage = $donnees['timestamp'] ?? null;

        return [[
            'provider_event_id' => (string) ($donnees['id'] ?? ''),
            'provider_message_id' => $this->identifiantDuMessage($donnees),
            'event_type' => $type,
            'occurred_at' => is_numeric($horodatage)
                ? Carbon::createFromTimestamp((int) $horodatage)->toDateTimeString()
                : null,
            'payload' => $donnees,
        ]];
    }

    /**
     * LE VOCABULAIRE DE MAILGUN VERS LE NÔTRE.
     *
     * `failed` couvre deux réalités très différentes : un rejet DÉFINITIF (adresse inexistante) et
     * un rejet TEMPORAIRE (boîte pleine). Les confondre marquerait comme perdue une adresse qui
     * fonctionne — seul le permanent compte comme rebond.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function typeNormalise(string $brut, array $donnees): ?string
    {
        if ($brut === 'failed') {
            return ((string) ($donnees['severity'] ?? '')) === 'permanent' ? 'bounced' : null;
        }

        return match ($brut) {
            'delivered' => 'delivered',
            'opened' => 'opened',
            'clicked' => 'clicked',
            'complained' => 'complained',
            default => null,
        };
    }

    /** @param array<string, mixed> $donnees */
    private function identifiantDuMessage(array $donnees): ?string
    {
        $message = (array) ($donnees['message'] ?? []);
        $entetes = (array) ($message['headers'] ?? []);

        $identifiant = (string) ($entetes['message-id'] ?? '');

        return $identifiant !== '' ? $identifiant : null;
    }
}

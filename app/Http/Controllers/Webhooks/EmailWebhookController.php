<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\EmailV2\Webhooks\ReceptionDesEvenementsEmail;
use App\Services\EmailV2\Webhooks\VerificateurDeSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LE POINT D'ENTRÉE DES RETOURS D'EXPÉDITION.
 *
 * C'est la porte qui manquait : les colonnes d'ouverture et de clic existaient sur les envois, la
 * table des événements aussi, et rien ne les alimentait.
 *
 * ELLE EST PUBLIQUE — un service d'expédition ne sait pas s'authentifier. Sa seule protection est
 * la SIGNATURE, et un fournisseur sans vérificateur est refusé plutôt qu'accepté « en attendant ».
 *
 * ELLE RÉPOND TOUJOURS VITE. Un fournisseur qui n'obtient pas de réponse rejoue, puis se met à
 * suspendre le point d'entrée : le traitement est donc court, et une charge illisible se solde par
 * un 202 — « reçu, rien compris » — plutôt que par une erreur qui déclencherait des rejeux inutiles.
 */
class EmailWebhookController extends Controller
{
    /** @param iterable<VerificateurDeSignature> $verificateurs */
    public function __construct(
        private readonly ReceptionDesEvenementsEmail $reception,
        private readonly iterable $verificateurs,
    ) {}

    public function __invoke(Request $requete, string $fournisseur): JsonResponse
    {
        $verificateur = $this->verificateurPour($fournisseur);

        if (! $verificateur instanceof VerificateurDeSignature) {
            // On ne dit PAS lesquels sont connus : une porte publique n'énumère pas ses serrures.
            return response()->json(['message' => 'Fournisseur non pris en charge.'], 404);
        }

        if (! $verificateur->verifie($requete)) {
            Log::warning('Webhook e-mail refusé : signature invalide.', [
                'fournisseur' => $fournisseur,
                'ip' => $requete->ip(),
            ]);

            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $evenements = $verificateur->evenements($requete);

        if ($evenements === []) {
            // 202 PLUTOT QU'UNE ERREUR : un type d'événement que nous n'exploitons pas n'est pas
            // une panne, et un 4xx ferait rejouer le fournisseur pour rien.
            return response()->json(['message' => 'Reçu, aucun événement exploitable.'], 202);
        }

        $bilan = $this->reception->recevoir($fournisseur, $evenements);

        return response()->json($bilan, 200);
    }

    private function verificateurPour(string $fournisseur): ?VerificateurDeSignature
    {
        foreach ($this->verificateurs as $verificateur) {
            if ($verificateur->fournisseur() === $fournisseur) {
                return $verificateur;
            }
        }

        return null;
    }
}

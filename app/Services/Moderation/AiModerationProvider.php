<?php

namespace App\Services\Moderation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LA MODÉRATION ASSISTÉE PAR IA (E32) — elle PROPOSE, l'administrateur DISPOSE.
 *
 * CE QUI EXISTE ET SA LIMITE. `ModerationService` bloque sur une liste de mots et masque les données
 * personnelles par expressions régulières. C'est rapide, prévisible, et complètement aveugle à tout
 * ce qui n'est pas littéral : une menace polie passe, une insulte mal orthographiée passe, et un
 * numéro de téléphone écrit en toutes lettres passe.
 *
 * L'IA NE BLOQUE JAMAIS TOUTE SEULE, ET C'EST LA DÉCISION CENTRALE DE CE MODULE. Elle produit un
 * verdict et une confiance ; le blocage automatique reste au ressort des règles déterministes, qui
 * sont explicables à quelqu'un dont on vient de masquer le message. Un faux positif automatique sur
 * une conversation entre un client et un prestataire casse une intervention en cours — et personne
 * ne saurait dire pourquoi.
 *
 * SOUS DRAPEAU, ET COUPÉ PAR DÉFAUT EN L'ABSENCE DE CLÉ. Un service de modération qui dépend d'un
 * tiers doit pouvoir disparaître sans emporter la messagerie : sans clé ou en cas de panne, le
 * verdict est `unknown` et la chaîne déterministe continue seule.
 *
 * LE TEXTE ANALYSÉ NE SORT PAS SANS RAISON. On n'envoie que le corps du message — jamais l'identité
 * des interlocuteurs, jamais l'identifiant de la conversation : un prestataire de modération n'a pas
 * besoin de savoir QUI parle pour dire si un texte est menaçant.
 */
class AiModerationProvider
{
    /** Ce qu'on rend quand l'IA n'est pas disponible : ni blanc-seing, ni condamnation. */
    public const VERDICT_INCONNU = 'unknown';

    public const VERDICT_SAIN = 'clean';

    /** À revoir par un humain — jamais bloqué d'office. */
    public const VERDICT_SUSPECT = 'flagged';

    /**
     * Analyser un texte.
     *
     * @return array{verdict: string, confidence: float, categories: list<string>, reason: string|null}
     */
    public function analyser(string $texte): array
    {
        if (! feature('ai_moderation')) {
            return $this->indisponible('feature_off');
        }

        $cle = (string) config('services.anthropic.key', '');

        if ($cle === '' || trim($texte) === '') {
            return $this->indisponible('no_key_or_empty');
        }

        try {
            $reponse = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $cle,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 200,
                    'system' => $this->consigne(),
                    // Le CORPS SEUL : un prestataire de modération n'a pas besoin de savoir qui
                    // parle pour dire si un texte est menaçant.
                    'messages' => [['role' => 'user', 'content' => $texte]],
                ]);

            if (! $reponse->successful()) {
                return $this->indisponible('http_'.$reponse->status());
            }

            $donnees = json_decode($this->extraireLeJson((string) data_get($reponse->json(), 'content.0.text', '')), true);

            if (! is_array($donnees)) {
                return $this->indisponible('unparseable');
            }

            $verdict = in_array($donnees['verdict'] ?? null, [self::VERDICT_SAIN, self::VERDICT_SUSPECT], true)
                ? (string) $donnees['verdict']
                : self::VERDICT_INCONNU;

            return [
                'verdict' => $verdict,
                'confidence' => (float) min(1.0, max(0.0, (float) ($donnees['confidence'] ?? 0))),
                'categories' => array_values(array_filter(
                    (array) ($donnees['categories'] ?? []),
                    fn ($c) => is_string($c),
                )),
                'reason' => isset($donnees['reason']) ? (string) $donnees['reason'] : null,
            ];
        } catch (\Throwable $e) {
            /*
             * SOFT-FAIL TOTAL. Un service de modération qui dépend d'un tiers doit pouvoir
             * disparaître sans emporter la messagerie : la chaîne déterministe continue seule.
             */
            Log::info('[ai_moderation] indisponible', ['error' => $e->getMessage()]);

            return $this->indisponible('exception');
        }
    }

    /**
     * @return array{verdict: string, confidence: float, categories: list<string>, reason: string|null}
     */
    protected function indisponible(string $raison): array
    {
        return [
            // NI BLANC-SEING, NI CONDAMNATION. Rendre `clean` ferait passer l'indisponibilité pour
            // une validation, ce qui est exactement le mensonge à éviter.
            'verdict' => self::VERDICT_INCONNU,
            'confidence' => 0.0,
            'categories' => [],
            'reason' => $raison,
        ];
    }

    protected function extraireLeJson(string $texte): string
    {
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');

        if ($debut === false || $fin === false || $fin < $debut) {
            return '{}';
        }

        return substr($texte, $debut, $fin - $debut + 1);
    }

    protected function consigne(): string
    {
        return <<<'TXT'
        Tu analyses un message échangé sur une plateforme de services à domicile, entre un client et
        un professionnel.

        Réponds UNIQUEMENT par un objet JSON, sans texte autour :
        {"verdict": "clean"|"flagged", "confidence": 0.0-1.0, "categories": ["harassment"|"threat"|"scam"|"off_platform"|"personal_data"], "reason": "<une phrase>"}

        Règles :
        - "flagged" signifie « à faire relire par un humain », JAMAIS « à bloquer ».
        - Un désaccord, une plainte ou un mécontentement ne sont PAS des motifs de signalement.
        - "off_platform" vise les tentatives d'emmener la transaction hors de la plateforme.
        - Dans le doute, réponds "clean" avec une confiance basse : un faux positif casse une
          intervention en cours.
        TXT;
    }
}

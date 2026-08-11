<?php

namespace App\Services\Ai;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DÉCRIRE SON BESOIN EN TEXTE, ET ARRIVER SUR LE BON MÉTIER (E5).
 *
 * LE PROBLÈME QU'IL RÈGLE. Le moteur de commande commence par « choisissez un secteur », puis « un
 * métier ». C'est parfait quand on sait qu'il faut un plafonneur ; ça ne l'est pas quand on écrit
 * « il y a une auréole marron au plafond de la salle de bain ». Le client abandonne à l'étape zéro,
 * ou choisit le mauvais métier et découvre l'erreur quand le professionnel arrive.
 *
 * LE CATALOGUE FAIT AUTORITÉ, PAS LE MODÈLE. On ne demande jamais à l'IA d'inventer un métier : on
 * lui donne la liste réelle et on n'accepte qu'un identifiant qui s'y trouve. Une réponse hors
 * catalogue est écartée — sans quoi le parcours partirait sur un métier qui n'existe pas, et
 * l'erreur ne se verrait qu'au moment de chercher un prestataire.
 *
 * IL PROPOSE, IL NE DÉCIDE PAS. Le résultat porte un niveau de confiance et pré-remplit le
 * parcours ; le client garde la main sur chaque étape. Une IA qui commanderait à sa place
 * transformerait une erreur d'interprétation en intervention non désirée.
 *
 * REPLI DÉTERMINISTE, TOUJOURS. Sans clé d'API, en cas de panne ou de dépassement de quota, la
 * recherche par mots-clés sur le catalogue prend le relais. Elle est moins fine et parfaitement
 * honnête sur sa confiance : un assistant qui tombe en panne doit dégrader, pas disparaître.
 */
class OrderIntentInterpreter
{
    /**
     * Interpréter une description libre.
     *
     * @return array<string, mixed>
     */
    public function interpreter(string $description): array
    {
        $description = trim($description);

        if ($description === '') {
            return $this->rien('Décrivez ce dont vous avez besoin.');
        }

        $catalogue = $this->catalogue();

        if ($catalogue->isEmpty()) {
            return $this->rien('Aucun service n’est ouvert pour le moment.');
        }

        $viaModele = $this->interpreterParLeModele($description, $catalogue);

        // Le repli n'est pas un cas dégradé exceptionnel : c'est le chemin normal quand aucune clé
        // n'est configurée, ce qui est l'état de la plupart des environnements.
        return $viaModele ?? $this->interpreterParMotsCles($description, $catalogue);
    }

    /**
     * Le catalogue RÉEL — c'est lui qui fait autorité.
     *
     * @return \Illuminate\Support\Collection<int, Trade>
     */
    protected function catalogue(): \Illuminate\Support\Collection
    {
        return Trade::query()
            ->where('is_active', true)
            ->with('sector:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'sector_id', 'description']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Trade>  $catalogue
     * @return array<string, mixed>|null  `null` quand le modèle n'est pas disponible
     */
    protected function interpreterParLeModele(string $description, \Illuminate\Support\Collection $catalogue): ?array
    {
        $cle = (string) config('services.anthropic.key', '');

        if ($cle === '') {
            return null;
        }

        $liste = $catalogue
            ->map(fn (Trade $metier) => sprintf('%d | %s | %s', $metier->id, $metier->name, $metier->sector->name ?? '—'))
            ->implode("\n");

        try {
            $reponse = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => $cle,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 400,
                    'system' => $this->consigne($liste),
                    'messages' => [['role' => 'user', 'content' => $description]],
                ]);

            if (! $reponse->successful()) {
                Log::info('[order_intent] réponse non exploitable', ['status' => $reponse->status()]);

                return null;
            }

            $texte = (string) data_get($reponse->json(), 'content.0.text', '');
            $donnees = json_decode($this->extraireLeJson($texte), true);

            if (! is_array($donnees)) {
                return null;
            }

            $metier = $catalogue->firstWhere('id', (int) ($donnees['trade_id'] ?? 0));

            if ($metier === null) {
                /*
                 * LE CATALOGUE FAIT AUTORITÉ. Un identifiant hors liste est écarté sans discussion :
                 * partir sur un métier qui n'existe pas produirait une commande que le dispatch ne
                 * saurait servir, et l'erreur ne se verrait qu'à la recherche de prestataire.
                 */
                Log::info('[order_intent] métier hors catalogue écarté', ['recu' => $donnees['trade_id'] ?? null]);

                return $this->interpreterParMotsCles($description, $catalogue);
            }

            return [
                'trade_id' => $metier->id,
                'trade_name' => $metier->name,
                'sector_id' => $metier->sector_id,
                'confidence' => $this->confianceLisible($donnees['confidence'] ?? null),
                'summary' => Str::limit((string) ($donnees['summary'] ?? ''), 200),
                'source' => 'model',
                // Ce que le modèle a compris et qu'on peut réutiliser comme réponses de départ.
                'hints' => is_array($donnees['hints'] ?? null) ? $donnees['hints'] : [],
            ];
        } catch (\Throwable $e) {
            // Soft-fail : un assistant qui tombe en panne doit dégrader, pas disparaître.
            report($e);

            return null;
        }
    }

    /**
     * LE REPLI DÉTERMINISTE — mots du catalogue contre mots de la description.
     *
     * Volontairement simple et explicable. Il ne prétend pas comprendre : il compte des
     * correspondances, et le dit en rendant une confiance basse.
     *
     * @param  \Illuminate\Support\Collection<int, Trade>  $catalogue
     * @return array<string, mixed>
     */
    protected function interpreterParMotsCles(string $description, \Illuminate\Support\Collection $catalogue): array
    {
        $mots = collect(preg_split('/[^\p{L}]+/u', Str::lower(Str::ascii($description))) ?: [])
            // Les mots de moins de quatre lettres n'apportent rien et font correspondre n'importe
            // quoi : « eau » toucherait autant le plombier que le nettoyeur de vitres.
            ->filter(fn (string $mot) => mb_strlen($mot) >= 4)
            ->unique();

        $meilleur = null;
        $meilleurScore = 0;

        foreach ($catalogue as $metier) {
            $corpus = Str::lower(Str::ascii(trim(($metier->name ?? '').' '.($metier->description ?? ''))));
            $score = $mots->filter(fn (string $mot) => str_contains($corpus, $mot))->count();

            if ($score > $meilleurScore) {
                $meilleur = $metier;
                $meilleurScore = $score;
            }
        }

        if ($meilleur === null) {
            return $this->rien('Nous n’avons pas reconnu le service. Choisissez-le dans la liste.');
        }

        return [
            'trade_id' => $meilleur->id,
            'trade_name' => $meilleur->name,
            'sector_id' => $meilleur->sector_id,
            // JAMAIS « haute » : ce repli compte des mots, il ne comprend pas une phrase. Annoncer
            // une confiance qu'on n'a pas ferait accepter une proposition sans la relire.
            'confidence' => $meilleurScore >= 2 ? 'medium' : 'low',
            'summary' => '',
            'source' => 'keywords',
            'hints' => [],
        ];
    }

    /** @return array<string, mixed> */
    protected function rien(string $message): array
    {
        return [
            'trade_id' => null,
            'trade_name' => null,
            'sector_id' => null,
            'confidence' => 'none',
            'summary' => $message,
            'source' => 'none',
            'hints' => [],
        ];
    }

    protected function confianceLisible(mixed $valeur): string
    {
        return in_array($valeur, ['high', 'medium', 'low'], true) ? (string) $valeur : 'medium';
    }

    /** Le modèle bavarde parfois autour du JSON : on prend ce qui est entre accolades. */
    protected function extraireLeJson(string $texte): string
    {
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');

        if ($debut === false || $fin === false || $fin < $debut) {
            return '{}';
        }

        return substr($texte, $debut, $fin - $debut + 1);
    }

    protected function consigne(string $liste): string
    {
        return <<<TXT
        Tu aides un client à trouver le bon service dans un catalogue FERMÉ.

        Catalogue disponible (id | métier | secteur) :
        {$liste}

        Réponds UNIQUEMENT par un objet JSON, sans texte autour :
        {"trade_id": <id du catalogue>, "confidence": "high"|"medium"|"low", "summary": "<reformulation en une phrase>", "hints": {"<code>": "<valeur>"}}

        Règles :
        - `trade_id` DOIT venir du catalogue ci-dessus. Si rien ne correspond, mets null.
        - `confidence` est "low" dès que la description est ambiguë.
        - `summary` reformule le besoin dans les mots du client, sans rien ajouter.
        - N'invente jamais de prix, de durée ni de disponibilité.
        TXT;
    }

    /** Les secteurs, pour situer le métier proposé dans le parcours. */
    public function secteurDe(?int $sectorId): ?Sector
    {
        return $sectorId === null ? null : Sector::query()->find($sectorId);
    }
}

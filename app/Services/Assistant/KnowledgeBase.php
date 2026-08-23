<?php

namespace App\Services\Assistant;

use Illuminate\Support\Collection;

/** Static knowledge base for the Brio chatbot assistant. */
class KnowledgeBase
{
    /** Search articles by query string (case-insensitive substring on title + content). */
    public function search(string $query): string
    {
        $articles = $this->matchingArticles($query)->take(3);

        if ($articles->isEmpty()) {
            return '';
        }

        $lines = $articles
            ->map(fn (array $a) => "- {$a['title']}: {$a['content']}")
            ->implode("\n");

        return "Base de connaissances:\n".$lines;
    }

    /**
     * Returns articles that match the query.
     *
     * @return Collection<int, array{title: string, content: string, tags: string[]}>
     */
    public function matchingArticles(string $query): Collection
    {
        $q = mb_strtolower($query);

        return collect($this->articles())
            ->filter(function (array $a) use ($q): bool {
                return str_contains(mb_strtolower($a['title']), $q)
                    || str_contains(mb_strtolower($a['content']), $q)
                    || collect($a['tags'] ?? [])->contains(fn (string $t) => str_contains($q, $t));
            });
    }

    /** @return array<int, array{title: string, content: string, tags: string[]}> */
    private function articles(): array
    {
        return [
            [
                'title' => 'Annulation',
                'content' => "Vous pouvez annuler gratuitement jusqu'a 24h avant. Entre 2h et 24h, des frais de 30% s'appliquent. Moins de 2h, 80% de frais.",
                'tags' => ['annul', 'cancel', 'remboursement', 'frais'],
            ],
            [
                'title' => 'Paiement',
                'content' => 'Le paiement est pre-autorise a la reservation et capture a la fin de la mission. Modes: carte bancaire via Stripe.',
                'tags' => ['paiement', 'carte', 'stripe', 'facture', 'payement'],
            ],
            [
                'title' => 'Fidelite',
                'content' => '1 point par euro depense. Tiers: Bronze (0), Silver (500), Gold (1500), Platinum (5000). Points echangeables contre des reductions.',
                'tags' => ['fidelite', 'points', 'bronze', 'silver', 'gold', 'platinum', 'reduction'],
            ],
            [
                'title' => 'Litige',
                'content' => 'En cas de probleme, ouvrez un litige dans les 48h. Un mediateur Brio intervient sous 24h. Resolution possible: remboursement, credit, nouvelle mission.',
                'tags' => ['litige', 'probleme', 'sav', 'plainte', 'mediateur'],
            ],
            [
                'title' => 'KYC',
                'content' => "Les prestataires doivent verifier leur identite (carte d'identite ou passeport) avant de recevoir des missions.",
                'tags' => ['kyc', 'identite', 'verification', 'prestataire'],
            ],
            [
                'title' => 'Stripe Connect',
                'content' => 'Les prestataires recoivent leurs paiements via Stripe Connect. Versements automatiques toutes les semaines.',
                'tags' => ['stripe', 'connect', 'virement', 'versement', 'paiement prestataire'],
            ],
            [
                'title' => 'RGPD',
                'content' => "Vous pouvez demander l'export ou la suppression de vos donnees depuis votre profil. Delai de grace de 30 jours pour la suppression.",
                'tags' => ['rgpd', 'gdpr', 'donnees', 'suppression', 'export', 'vie privee'],
            ],
            [
                'title' => 'Metiers disponibles',
                'content' => "Brio couvre 12 metiers: nettoyage, peinture, batiment, plomberie, electricite, jardinage, demenagement, garde d'enfants, toiture, levage, renovation, securite.",
                'tags' => ['metier', 'service', 'nettoyage', 'peinture', 'plomberie', 'electricite', 'jardinage'],
            ],
            [
                'title' => 'Horaires',
                'content' => 'Les missions sont disponibles 7j/7 de 6h a 22h. Les creneaux dependent de la disponibilite des prestataires dans votre zone.',
                'tags' => ['horaire', 'disponibilite', 'creneau', 'heure'],
            ],
            [
                'title' => 'Zones',
                'content' => "Brio est disponible en Belgique (Bruxelles, Wallonie, Flandre), en France et s'etend progressivement.",
                'tags' => ['zone', 'belgique', 'france', 'bruxelles', 'region', 'ville'],
            ],
        ];
    }
}

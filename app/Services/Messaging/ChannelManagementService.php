<?php

namespace App\Services\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CRÉER UNE CONVERSATION, Y METTRE DU MONDE, EN RETIRER — POUR LES DEUX SURFACES.
 *
 * Toute la gestion des canaux vivait dans `TeamChannels.php`, l'écran web : créer, ajouter un
 * membre, ouvrir une conversation à deux. L'API mobile ne savait que LISTER, LIRE et POSTER — une
 * équipe sur le terrain pouvait donc répondre, jamais ouvrir un fil. Recopier ces règles côté API
 * aurait produit deux définitions de « qui peut entrer dans un canal ».
 *
 * TROIS INVARIANTS, ET AUCUN N'EST DÉCORATIF :
 *
 *   1. LE CANAL APPARTIENT À UNE ORGANISATION, et n'est jamais chargé hors d'elle. Ouvrir l'ajout
 *      de membres sans ce scoping ouvrirait les canaux d'une société aux utilisateurs de toutes
 *      les autres.
 *   2. LA CIBLE EST UN COLLÈGUE ACTIF. Un canal d'équipe qui accepterait n'importe quel compte
 *      cesserait d'être une conversation interne.
 *   3. ON CHERCHE AVANT DE CRÉER une conversation à deux. Sans cela, chaque clic ajouterait un
 *      canal : la messagerie se remplirait de fils vides entre les deux mêmes personnes, et
 *      l'historique se disperserait entre eux — pire que pas de messagerie du tout.
 */
class ChannelManagementService
{
    /**
     * Créer un canal, et y embarquer qui de droit.
     *
     * @param  bool  $avecTouteLEquipe  sans cela, un canal d'équipe naît vide de son équipe et il
     *                                  faut ajouter chaque collègue un par un
     */
    public function creer(
        User $acteur,
        int $organisationId,
        string $nom,
        string $type = 'team',
        bool $prive = false,
        bool $avecTouteLEquipe = false,
    ): Channel {
        return DB::transaction(function () use ($acteur, $organisationId, $nom, $type, $prive, $avecTouteLEquipe) {
            $canal = Channel::create([
                'organization_account_id' => $organisationId,
                'name' => $nom,
                'type' => $type,
                'is_private' => $prive,
                'created_by' => $acteur->id,
            ]);

            $canal->members()->attach($acteur->id, ['role' => 'owner']);

            if ($avecTouteLEquipe) {
                $coequipiers = OrganizationMember::query()
                    ->where('organization_account_id', $organisationId)
                    ->where('status', 'active')
                    ->where('user_id', '!=', $acteur->id)
                    ->pluck('user_id');

                foreach ($coequipiers as $coequipierId) {
                    $canal->members()->syncWithoutDetaching([$coequipierId => ['role' => 'member']]);
                }
            }

            /*
             * Le message système passe par `Message::create()` et NON par `MessageService::send()`.
             *
             * `send()` déclenche mentions, notifications et diffusion : une ligne d'annonce
             * technique ne doit réveiller personne, et la modération n'a pas à examiner un texte
             * que le produit a écrit lui-même.
             */
            Message::create([
                'channel_id' => $canal->id,
                'user_id' => $acteur->id,
                'content' => "Canal **#{$canal->name}** créé par {$acteur->name}.",
                'type' => Message::TYPE_SYSTEM,
            ]);

            return $canal;
        });
    }

    /**
     * OUVRIR — OU RETROUVER — LA CONVERSATION À DEUX.
     *
     * La recherche porte sur la COMPOSITION, pas sur un nom : « exactement ces deux-là, et personne
     * d'autre ». C'est ce qui fait qu'Ana retrouve la conversation ouverte par son patron plutôt
     * que d'en créer une seconde en croyant lui répondre.
     */
    public function ouvrirConversationDirecte(User $acteur, int $organisationId, int $autreUserId): ?Channel
    {
        if ($autreUserId === $acteur->id) {
            return null;
        }

        $collegue = $this->collegueActif($organisationId, $autreUserId);

        if ($collegue === null) {
            return null;
        }

        /*
         * Le comptage final se fait EN PHP, et non par un `having` sur `withCount`.
         *
         * SQLite refuse « HAVING clause on a non-aggregate query » là où MySQL l'accepte : la suite
         * tourne sur SQLite, la production sur MySQL, et écrire la requête qui plaît aux deux
         * coûterait plus cher en subtilité qu'un filtre sur une poignée de canaux privés.
         */
        $existant = Channel::query()
            ->where('organization_account_id', $organisationId)
            ->where('type', 'private')
            ->whereHas('members', fn ($q) => $q->where('user_id', $acteur->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $autreUserId))
            ->withCount('members')
            ->get()
            // « Exactement ces deux-là » : un canal privé à trois n'est pas cette conversation.
            ->firstWhere('members_count', 2);

        if ($existant !== null) {
            return $existant;
        }

        return DB::transaction(function () use ($acteur, $organisationId, $autreUserId, $collegue) {
            $canal = Channel::create([
                'organization_account_id' => $organisationId,
                // Le nom sert l'affichage, pas l'identité : c'est la composition qui identifie la
                // conversation. Deux homonymes ne se retrouveraient pas dans le même canal.
                'name' => $collegue->user->name,
                'type' => 'private',
                'is_private' => true,
                'created_by' => $acteur->id,
            ]);

            $canal->members()->attach([
                $acteur->id => ['role' => 'owner'],
                $autreUserId => ['role' => 'member'],
            ]);

            return $canal;
        });
    }

    /**
     * Ajouter quelqu'un à un canal.
     *
     * @return bool `false` si la cible n'est pas un collègue actif — on ne dit pas laquelle des
     *              deux conditions a manqué, la différence renseignerait sur l'effectif d'autrui
     */
    public function ajouterMembre(Channel $canal, int $userId): bool
    {
        if ($this->collegueActif((int) $canal->organization_account_id, $userId) === null) {
            return false;
        }

        // `syncWithoutDetaching` : ajouter deux fois la même personne ne duplique pas la ligne, et
        // le geste reste rejouable — ce qui compte quand deux gérants agissent en même temps.
        $canal->members()->syncWithoutDetaching([$userId => ['role' => 'member']]);

        return true;
    }

    /**
     * Retirer quelqu'un d'un canal.
     *
     * RETIRER COUPE AUSSI L'ACCÈS TEMPS RÉEL : l'autorisation Reverb `channel.{id}` vérifie
     * l'appartenance à chaque abonnement. C'est la raison pour laquelle on détache réellement la
     * ligne au lieu de poser un drapeau — un membre « inactif » resterait dans le canal aux yeux
     * de la diffusion.
     */
    public function retirerMembre(Channel $canal, int $userId): void
    {
        $canal->members()->detach($userId);
    }

    /**
     * Marquer un canal comme lu.
     *
     * `channel_members.last_read_at` existait depuis l'origine et n'était écrit par personne : les
     * non-lus ne pouvaient donc pas exister, et la liste des canaux ne disait jamais où il se
     * passait quelque chose.
     */
    public function marquerCommeLu(Channel $canal, int $userId): void
    {
        $canal->members()->updateExistingPivot($userId, ['last_read_at' => now()]);
    }

    /**
     * Les non-lus de chaque canal de l'organisation où cette personne est membre.
     *
     * SES PROPRES MESSAGES NE COMPTENT PAS — se compter soi-même afficherait un badge à chaque fois
     * qu'on parle. Et `last_read_at` nul signifie « jamais ouvert », donc tout est non lu : c'est
     * la valeur d'un canal auquel on vient d'être ajouté.
     *
     * @return array<int, int> channel_id => non-lus
     */
    public function nonLusPour(int $organisationId, int $userId): array
    {
        $canaux = Channel::query()
            ->where('organization_account_id', $organisationId)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        /*
         * La date de dernière lecture est lue sur le PIVOT, en une requête.
         *
         * Passer par `$canal->members->first()->pivot` obligerait à charger chaque relation, et
         * PHPStan a raison de refuser cet accès : `members()` rend des `User`, dont le pivot n'est
         * typé nulle part. La table de liaison se lit directement, ce qui est aussi plus honnête
         * sur ce qu'on cherche.
         */
        $lectures = DB::table('channel_members')
            ->whereIn('channel_id', $canaux)
            ->where('user_id', $userId)
            ->pluck('last_read_at', 'channel_id');

        $comptes = [];

        foreach ($canaux as $canalId) {
            $depuis = $lectures[$canalId] ?? null;

            $comptes[$canalId] = Message::query()
                ->where('channel_id', $canalId)
                ->where('user_id', '!=', $userId)
                // Les lignes système ne sont pas des messages : annoncer « 1 non-lu » pour la
                // création du canal qu'on vient d'ouvrir serait du bruit.
                ->where('type', '!=', Message::TYPE_SYSTEM)
                ->when($depuis !== null, fn ($q) => $q->where('created_at', '>', $depuis))
                ->count();
        }

        return $comptes;
    }

    private function collegueActif(int $organisationId, int $userId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with('user')
            ->first();
    }
}

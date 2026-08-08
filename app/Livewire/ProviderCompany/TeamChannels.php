<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Messaging\MarkdownRenderer;
use App\Services\Messaging\ChannelManagementService;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\ModerationService;
use App\Services\Messaging\ReactionService;
use App\Services\Messaging\ReadReceiptService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class TeamChannels extends Component
{
    use EnforcesActiveOrgMembership;
    use WithFileUploads;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public int $activeChannelId = 0;

    public string $messageInput = '';

    public bool $showNewChannel = false;

    public string $newChannelName = '';

    public string $newChannelType = Channel::TYPE_TEAM;

    public bool $isPrivate = false;

    /** Embarquer toute l'équipe active à la création du canal. */
    public bool $inviteWholeTeam = false;

    /** Ouvre le panneau de gestion des membres du canal actif. */
    public bool $showMembersPanel = false;

    public ?int $editingMessageId = null;

    public string $editContent = '';

    public ?int $replyingToId = null;

    /** @var Collection */
    public $channels;

    public array $messages = [];

    public array $membersList = [];

    private OrganizationAccount $org;

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    /**
     * L'ORGANISATION SE RÉSOUT À CHAQUE REQUÊTE, PAS SEULEMENT AU MONTAGE (corrigé le 2026-08-05).
     *
     * `$org` est une propriété privée typée : Livewire n'hydrate que les propriétés PUBLIQUES, et
     * `mount()` ne s'exécute qu'au tout premier rendu. À la deuxième requête — c'est-à-dire à la
     * première interaction de l'utilisateur — la propriété était donc non initialisée, et toute
     * méthode la touchant levait « Typed property $org must not be accessed before initialization ».
     *
     * Cela condamnait TOUT le composant au-delà de l'affichage : créer un canal, en ouvrir un,
     * envoyer et supprimer un message passent tous par `$this->org`. La messagerie d'équipe
     * n'était pas seulement incapable d'accueillir un second membre (bug 3) — elle ne
     * fonctionnait pas du tout.
     *
     * Le fichier de test existant paraissait couvrir `createChannel()` : ses deux cas s'arrêtent
     * en réalité sur la validation, avant la ligne fautive. Le chemin nominal n'était pas testé.
     *
     * `boot()` s'exécute, lui, à chaque requête Livewire.
     */
    public function boot(): void
    {
        $org = Auth::user()?->currentOrganization;

        // Un utilisateur sans organisation n'a rien à faire ici : 403 explicite plutôt qu'une
        // TypeError sur l'affectation d'une propriété non nullable.
        abort_if($org === null, 403);

        $this->org = $org;
    }

    public function mount(): void
    {
        $user = Auth::user();

        // Vérifier permission
        abort_unless(
            app(PermissionService::class)->can($user, 'channels.create', $this->org),
            403
        );

        $this->loadChannels();

        // Ouvrir le premier canal automatiquement
        if ($this->channels->isNotEmpty() && ! $this->activeChannelId) {
            $this->openChannel($this->channels->first()->id);
        }
    }

    // ──────────────────────────────────────────────────────
    // Channels
    // ──────────────────────────────────────────────────────
    public function loadChannels(): void
    {
        $user = Auth::user();

        $this->channels = Channel::forOrg($this->org->id)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->whereDoesntHave('readBy', fn ($r) => $r->where('user_id', $user->id));
            }])
            ->orderBy('name')
            ->get();
    }

    /**
     * CE COMPOSANT OUVRAIT N'IMPORTE QUEL CANAL, Y COMPRIS CEUX DES AUTRES SOCIÉTÉS
     * (corrigé le 2026-08-05).
     *
     * `openChannel()` acceptait un identifiant arbitraire : ni l'organisation ni l'appartenance
     * n'étaient vérifiées. Il chargeait ensuite les messages, marquait le canal comme lu et
     * exposait sa liste de membres. Comme `$activeChannelId` est une propriété PUBLIQUE — donc
     * modifiable depuis le navigateur — la fuite ne demandait même pas de cliquer.
     *
     * `ChannelPolicy::view()` encodait déjà la règle attendue (être membre) : personne ne
     * l'appelait. La barre latérale, elle, ne liste que les canaux de l'organisation dont
     * l'utilisateur est membre — la garde ci-dessous ne fait donc qu'aligner l'action sur ce que
     * l'interface propose déjà.
     */
    public function openChannel(int $channelId): void
    {
        $canalAutorise = Channel::query()
            ->where('organization_account_id', $this->org->id)
            ->find($channelId);

        if (! $canalAutorise || ! Auth::user()->can('view', $canalAutorise)) {
            return;
        }

        $this->activeChannelId = $channelId;
        $this->replyingToId = null;
        $this->editingMessageId = null;
        $this->loadMessages();

        // Phase 4.1 — Marquer comme lu
        $channel = Channel::find($channelId);
        if ($channel) {
            app(ReadReceiptService::class)->markChannelAsRead(Auth::user(), $channel);
        }

        // Marquer comme lu
        $channel = Channel::find($channelId);
        $channel?->markReadFor(Auth::user());

        // Charger la liste des membres pour les @mentions
        $this->membersList = $channel?->members()
            ->select(['users.id', 'users.name'])
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->toArray();

        $this->loadChannels(); // Rafraîchir les compteurs non lus
    }

    /**
     * Le nom affichable d'un expéditeur, qui peut ne plus exister.
     *
     * `messages.user_id` est nullable et `nullOnDelete` : les messages d'un compte supprimé
     * restent en place, sans expéditeur. Le repli n'est donc pas décoratif.
     *
     * Sur l'écriture elle-même, PHPStan avait raison et j'avais tort : `??` évalue son membre
     * gauche avec la sémantique d'`isset()`, qui tolère déjà un `null` dans la chaîne. L'opérateur
     * `?->` y était redondant, pas protecteur — j'avais pris un doublon d'opérateurs pour une
     * mauvaise inférence de nullabilité.
     */
    private function nomExpediteur(?User $expediteur): string
    {
        return $expediteur->name ?? 'Utilisateur supprimé';
    }

    public function loadMessages(): void
    {
        if (! $this->activeChannelId) {
            $this->messages = [];

            return;
        }

        $renderer = app(MarkdownRenderer::class);

        $this->messages = Message::query()
            ->where('channel_id', $this->activeChannelId)
            ->topLevel()
            // `parent.sender` accompagne `reply_to` : sans lui, afficher 50 réponses déclencherait
            // 100 requêtes supplémentaires.
            // `profile_photo_path` doit figurer dans la sélection : sans elle l'accesseur
            // `profile_photo_url` retombe toujours sur l'avatar par défaut, silencieusement.
            // `parent.sender` accompagne `reply_to` : sans lui, afficher 50 réponses déclencherait
            // 100 requêtes supplémentaires.
            ->with([
                'sender:id,name,profile_photo_path',
                'mentions',
                'attachments',
                'reactions.user:id,name',
                'parent.sender:id,name',
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'content_html' => $renderer->render($m->content, $m->mentions),  // ← NOUVEAU
                'is_pinned' => (bool) $m->is_pinned,                           // ← NOUVEAU
                'pinned_by' => $m->pinned_by,
                'sender_id' => $m->user_id,
                'sender_name' => $m->sender?->name,
                /*
                 * CINQ CLÉS QUE LA VUE LISAIT SANS QUE PERSONNE NE LES PRODUISE (corrigé le 2026-08-05).
                 *
                 * `team-channels.blade.php` consomme `sender`, `date`, `avatar`, `is_system` et
                 * `reply_to` ; `loadMessages()` n'en fournissait aucune. La vue et le composant
                 * ont été écrits contre deux contrats différents, et le rendu s'arrêtait sur
                 * « Undefined array key "date" » dès le premier message — y compris le message
                 * système émis à la création d'un canal.
                 *
                 * Autrement dit, personne n'a jamais vu une conversation s'afficher.
                 */
                'sender' => $this->nomExpediteur($m->sender),
                'avatar' => $m->sender?->profile_photo_url,
                'date' => $m->created_at->translatedFormat('d F Y'),
                'is_system' => $m->type === Message::TYPE_SYSTEM,
                'reply_to' => $m->parent ? [
                    'sender' => $this->nomExpediteur($m->parent->sender),
                    'content' => Str::limit((string) $m->parent->content, 80),
                ] : null,
                'is_mine' => $m->user_id === Auth::id(),
                'is_edited' => $m->isEdited(),
                'time' => $m->created_at->format('H:i'),
                'replies_count' => $m->replies_count,
                'attachments' => $m->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'size' => $a->human_size,
                    'mime' => $a->mime_type,
                    'is_image' => $a->isImage(),
                    'is_ready' => $a->isReady(),
                    'is_infected' => $a->isInfected(),
                    'thumbnail' => $a->thumbnail_url,
                    'download_url' => $a->signed_url,
                ])->all(),
                'reactions' => app(ReactionService::class)->summarize($m, Auth::user()),
            ])
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────
    // Envoyer un message
    // ──────────────────────────────────────────────────────
    public function sendMessage(): void
    {
        $content = trim($this->messageInput);

        if (blank($content) || ! $this->activeChannelId) {
            return;
        }

        /*
         * FERMER LA LECTURE NE FERMAIT PAS L'ÉCRITURE (corrigé le 2026-08-06).
         *
         * La phase 0 a gardé `openChannel()` et le rendu. Ce chemin-ci résolvait encore le canal
         * par `Channel::find()` — sans organisation, sans politique — et `MessageService::send()`
         * n'autorise rien de son côté. `$activeChannelId` étant une propriété PUBLIQUE Livewire,
         * il suffisait de l'écrire depuis le navigateur pour publier dans le canal privé d'une
         * société concurrente, sans jamais l'ouvrir.
         *
         * `ChannelPolicy::postMessage()` encodait déjà la règle exacte — être membre, canal ni
         * verrouillé ni archivé — et n'était appelée nulle part.
         *
         * NOTE : `lockChannel()` et `archiveChannel()` résolvent le canal de la même façon, mais
         * passent par `ModerationService`, qui refuse un utilisateur non autorisé. Elles ne
         * présentent donc pas ce défaut — vérifié avant de conclure.
         */
        $channel = Channel::query()
            ->where('organization_account_id', $this->org->id)
            ->find($this->activeChannelId);

        if (! $channel || ! Auth::user()->can('postMessage', $channel)) {
            return;
        }

        // Phase 4 — MessageService gère TOUT en une transaction :
        //   - création du message (avec parent_id pour threads)
        //   - extraction des @user mentions et stockage en message_mentions
        //   - notification aux utilisateurs mentionnés (database + email)
        //   - mise à jour de replies_count + last_reply_at sur le parent
        //   - broadcast Reverb (MessageSent + UserMentioned)
        app(MessageService::class)->send(
            channel: $channel,
            sender: Auth::user(),
            content: $content,
            parentId: $this->replyingToId,
        );

        $this->messageInput = '';
        $this->replyingToId = null;

        $this->loadMessages();
    }

    public function pinMessage(int $messageId): void
    {
        $msg = Message::find($messageId);
        if (! $msg) {
            return;
        }

        try {
            app(ModerationService::class)->pinMessage(Auth::user(), $msg);
            $this->loadMessages();
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function unpinMessage(int $messageId): void
    {
        $msg = Message::find($messageId);
        if (! $msg) {
            return;
        }

        try {
            app(ModerationService::class)->unpinMessage(Auth::user(), $msg);
            $this->loadMessages();
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function lockChannel(): void
    {
        if (! $this->activeChannelId) {
            return;
        }
        $channel = Channel::find($this->activeChannelId);
        if (! $channel) {
            return;
        }

        try {
            app(ModerationService::class)->lockChannel(Auth::user(), $channel, ! $channel->is_locked);
            $this->loadChannels();
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function archiveChannel(): void
    {
        if (! $this->activeChannelId) {
            return;
        }
        $channel = Channel::find($this->activeChannelId);
        if (! $channel) {
            return;
        }

        try {
            app(ModerationService::class)->archiveChannel(Auth::user(), $channel, ! $channel->is_archived);
            $this->loadChannels();
            $this->activeChannelId = 0;
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────
    // Éditer / Supprimer
    // ──────────────────────────────────────────────────────
    public function startEdit(int $messageId): void
    {
        $msg = Message::find($messageId);

        if ($msg?->user_id !== Auth::id()) {
            return;
        }

        $this->editingMessageId = $messageId;
        $this->editContent = $msg->content;
    }

    public function saveEdit(): void
    {
        if (! $this->editingMessageId) {
            return;
        }

        $msg = Message::find($this->editingMessageId);

        if ($msg?->user_id !== Auth::id()) {
            return;
        }

        $content = trim($this->editContent);

        if (blank($content)) {
            return;
        }

        $msg->update([
            'content' => $content,
            'edited_at' => now(),
        ]);

        $this->editingMessageId = null;
        $this->editContent = '';
        $this->loadMessages();
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
        $this->editContent = '';
    }

    public function deleteMessage(int $messageId): void
    {
        $msg = Message::find($messageId);

        if (! $msg) {
            return;
        }

        $user = Auth::user();
        $isOwner = $user->membershipIn($this->org)?->isOwner();

        if ($msg->user_id !== $user->id && ! $isOwner) {
            return;
        }

        $msg->delete();
        $this->loadMessages();
    }

    // ──────────────────────────────────────────────────────
    // Réactions
    // ──────────────────────────────────────────────────────
    public function toggleReaction(int $messageId, string $emoji): void
    {
        $existing = MessageReaction::where([
            'message_id' => $messageId,
            'user_id' => Auth::id(),
            'emoji' => $emoji,
        ])->first();

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => Auth::id(),
                'emoji' => $emoji,
            ]);
        }

        $this->loadMessages();
    }

    public function setReplyTo(?int $messageId): void
    {
        $this->replyingToId = $messageId;
    }

    // ──────────────────────────────────────────────────────
    // Créer un canal
    // ──────────────────────────────────────────────────────
    public function createChannel(): void
    {
        $user = Auth::user();

        $this->validate([
            'newChannelName' => ['required', 'string', 'max:50'],
            'newChannelType' => ['required', 'in:team,mission,support,private,announcement'],
        ]);

        abort_unless(
            app(PermissionService::class)->can($user, 'channels.create', $this->org),
            403
        );

        /*
         * LA CRÉATION EST PARTIE DANS `ChannelManagementService`.
         *
         * L'API mobile doit ouvrir des fils elle aussi — une équipe sur le terrain pouvait
         * RÉPONDRE, jamais ouvrir. Deux implémentations de « qui entre dans un canal » auraient
         * divergé, et la divergence se serait vue du côté le plus permissif.
         */
        $channel = app(ChannelManagementService::class)->creer(
            acteur: $user,
            organisationId: (int) $this->org->id,
            nom: $this->newChannelName,
            type: $this->newChannelType,
            prive: $this->isPrivate,
            avecTouteLEquipe: $this->inviteWholeTeam,
        );

        $this->newChannelName = '';
        $this->showNewChannel = false;
        $this->loadChannels();
        $this->openChannel($channel->id);
    }

    /**
     * OUVRIR — OU RETROUVER — LA CONVERSATION À DEUX AVEC UN COLLÈGUE.
     *
     * Le type `private` existait depuis le début, et rien ne permettait d'en ouvrir une : pour dire
     * un mot à quelqu'un il fallait créer un canal nommé, ce que personne ne fait. Les équipes
     * passaient donc par WhatsApp — hors de l'outil, hors de toute trace, et hors de la modération.
     *
     * ON CHERCHE AVANT DE CRÉER, et c'est le cœur de la méthode. Sans cela, chaque clic ajouterait
     * un canal : la messagerie se remplirait de conversations vides entre les deux mêmes personnes,
     * et l'historique se disperserait entre elles — pire que pas de messagerie du tout.
     *
     * La recherche porte sur la COMPOSITION, pas sur un nom : « exactement ces deux-là, et personne
     * d'autre ». C'est ce qui fait qu'Ana retrouve la conversation ouverte par son patron plutôt
     * que d'en créer une seconde en croyant lui répondre.
     */
    public function ouvrirConversationDirecte(int $userId): void
    {
        $canal = app(ChannelManagementService::class)->ouvrirConversationDirecte(
            acteur: Auth::user(),
            organisationId: (int) $this->org->id,
            autreUserId: $userId,
        );

        if ($canal === null) {
            return;
        }

        $this->loadChannels();
        $this->openChannel($canal->id);
    }

    // ──────────────────────────────────────────────────────
    // Membres d'un canal
    // ──────────────────────────────────────────────────────

    /**
     * AJOUTER QUELQU'UN À UN CANAL — CE QUE RIEN NE SAVAIT FAIRE (ajouté le 2026-08-05).
     *
     * L'unique `members()->attach` du dépôt était celui du créateur, dans `createChannel()`.
     * Aucun écran, service ou commande ne permettait d'ajouter un tiers : chaque canal restait
     * un monologue. `ChannelPolicy` exposait pourtant `kickMember()` et `changeRole()` — pour une
     * population qui ne pouvait pas exister.
     *
     * La garde s'appuie sur `channels.manage`, une clé déjà présente dans la matrice de
     * `PermissionService` mais qu'aucun composant ne consommait : elle était accordée à trois
     * rôles sans jamais rien ouvrir.
     */
    public function addChannelMember(int $channelId, int $userId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'channels.manage', $this->org),
            403
        );

        // Le canal doit appartenir à l'organisation active.
        $canal = Channel::query()
            ->where('organization_account_id', $this->org->id)
            ->find($channelId);

        if (! $canal) {
            return;
        }

        /*
         * … et la personne ajoutée doit être une collègue. Sans ce filtre, ouvrir l'ajout de
         * membres ouvrirait les canaux d'une société aux utilisateurs de toutes les autres. La
         * règle vit dans le service, partagée avec l'API mobile.
         */
        app(ChannelManagementService::class)->ajouterMembre($canal, $userId);

        $this->dispatch('channel-members-updated');
    }

    /**
     * Retirer un membre. `ChannelPolicy::kickMember()` protège déjà l'owner du canal ; on la
     * consulte plutôt que de réimplémenter cette règle ici.
     */
    public function removeChannelMember(int $channelId, int $userId): void
    {
        $acteur = Auth::user();

        $canal = Channel::query()
            ->where('organization_account_id', $this->org->id)
            ->find($channelId);

        $cible = $canal ? User::find($userId) : null;

        if (! $canal || ! $cible) {
            return;
        }

        abort_unless($acteur->can('kickMember', [$canal, $cible]), 403);

        app(ChannelManagementService::class)->retirerMembre($canal, $userId);

        $this->dispatch('channel-members-updated');
    }

    public function toggleMembersPanel(): void
    {
        $this->showMembersPanel = ! $this->showMembersPanel;
    }

    // ──────────────────────────────────────────────────────
    // Écoute WebSocket (Reverb)
    // ──────────────────────────────────────────────────────
    #[On('echo-private:channel.{activeChannelId},MessageSent')]
    public function onNewMessage(array $data): void
    {
        $this->loadMessages();
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        /*
         * Le rendu est scopé lui aussi : `$activeChannelId` étant une propriété publique, un
         * client peut l'écrire directement sans jamais passer par `openChannel()`. Garder la
         * seule action laisserait la fuite ouverte par la porte d'à côté.
         */
        $activeChannel = $this->activeChannelId
            ? Channel::with('members:id,name,profile_photo_path')
                ->where('organization_account_id', $this->org->id)
                ->find($this->activeChannelId)
            : null;

        if ($activeChannel && ! Auth::user()->can('view', $activeChannel)) {
            $activeChannel = null;
        }

        // Les coéquipiers encore absents du canal : matière première du panneau « Membres ».
        $coequipiersAjoutables = $activeChannel
            ? User::query()
                ->whereIn('id', OrganizationMember::query()
                    ->where('organization_account_id', $this->org->id)
                    ->where('status', 'active')
                    ->pluck('user_id'))
                ->whereNotIn('id', $activeChannel->members->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('livewire.provider-company.team-channels', [
            'activeChannel' => $activeChannel,
            'coequipiersAjoutables' => $coequipiersAjoutables,
        ])->layout('layouts.provider-company');
    }
}

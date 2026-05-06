<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Services\Messaging\MessageService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\Messaging\ModerationService;
use App\Services\Messaging\ReadReceiptService;
use App\Services\Messaging\MarkdownRenderer;

class TeamChannels extends Component
{
    use WithFileUploads;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public ?int    $activeChannelId = null;
    public string  $messageInput    = '';
    public bool    $showNewChannel  = false;
    public string  $newChannelName  = '';
    public string  $newChannelType  = Channel::TYPE_TEAM;
    public bool    $isPrivate       = false;
    public ?int    $editingMessageId = null;
    public string  $editContent     = '';
    public ?int    $replyingToId    = null;

    /** @var \Illuminate\Database\Eloquent\Collection */
    public $channels;

    /** @var array */
    public array $messages = [];

    /** @var array */
    public array $membersList = [];

    private OrganizationAccount $org;

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        $user      = Auth::user();
        $this->org = $user->currentOrganization;

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
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->whereDoesntHave('readBy', fn($r) => $r->where('user_id', $user->id));
            }])
            ->orderBy('name')
            ->get();
    }

    public function openChannel(int $channelId): void
    {
        $this->activeChannelId = $channelId;
        $this->replyingToId    = null;
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
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
            ->toArray();

        $this->loadChannels(); // Rafraîchir les compteurs non lus
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
            ->with(['sender:id,name', 'mentions', 'attachments', 'reactions.user:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'id'           => $m->id,
                'content'      => $m->content,
                'content_html' => $renderer->render($m->content, $m->mentions),  // ← NOUVEAU
                'is_pinned'    => (bool) $m->is_pinned,                           // ← NOUVEAU
                'pinned_by'    => $m->pinned_by,
                'sender_id'    => $m->user_id,
                'sender_name'  => $m->sender?->name,
                'is_mine'      => $m->user_id === Auth::id(),
                'is_edited'    => $m->isEdited(),
                'time'         => $m->created_at->format('H:i'),
                'replies_count' => $m->replies_count,
                'attachments'   => $m->attachments->map(fn($a) => [
                    'id'           => $a->id,
                    'name'         => $a->original_name,
                    'size'         => $a->human_size,
                    'mime'         => $a->mime_type,
                    'is_image'     => $a->isImage(),
                    'is_ready'     => $a->isReady(),
                    'is_infected'  => $a->isInfected(),
                    'thumbnail'    => $a->thumbnail_url,
                    'download_url' => $a->signed_url,
                ])->all(),
                'reactions'    => app(\App\Services\Messaging\ReactionService::class)->summarize($m, Auth::user()),
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

        $channel = Channel::find($this->activeChannelId);
        if (! $channel) {
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

        $this->messageInput  = '';
        $this->replyingToId  = null;

        $this->loadMessages();
    }


    public function pinMessage(int $messageId): void
    {
        $msg = Message::find($messageId);
        if (! $msg) return;

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
        if (! $msg) return;

        try {
            app(ModerationService::class)->unpinMessage(Auth::user(), $msg);
            $this->loadMessages();
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function lockChannel(): void
    {
        if (! $this->activeChannelId) return;
        $channel = Channel::find($this->activeChannelId);
        if (! $channel) return;

        try {
            app(ModerationService::class)->lockChannel(Auth::user(), $channel, ! $channel->is_locked);
            $this->loadChannels();
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function archiveChannel(): void
    {
        if (! $this->activeChannelId) return;
        $channel = Channel::find($this->activeChannelId);
        if (! $channel) return;

        try {
            app(ModerationService::class)->archiveChannel(Auth::user(), $channel, ! $channel->is_archived);
            $this->loadChannels();
            $this->activeChannelId = null;
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
        $this->editContent      = $msg->content;
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
            'content'   => $content,
            'edited_at' => now(),
        ]);

        $this->editingMessageId = null;
        $this->editContent      = '';
        $this->loadMessages();
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
        $this->editContent      = '';
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
        $existing = \App\Models\MessageReaction::where([
            'message_id' => $messageId,
            'user_id'    => Auth::id(),
            'emoji'      => $emoji,
        ])->first();

        if ($existing) {
            $existing->delete();
        } else {
            \App\Models\MessageReaction::create([
                'message_id' => $messageId,
                'user_id'    => Auth::id(),
                'emoji'      => $emoji,
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

        $channel = Channel::create([
            'organization_account_id' => $this->org->id,
            'name'                    => $this->newChannelName,
            'type'                    => $this->newChannelType,
            'is_private'              => $this->isPrivate,
            'created_by'              => $user->id,
        ]);

        // Ajouter le créateur comme membre owner
        $channel->members()->attach($user->id, ['role' => 'owner']);

        // Message système de création
        Message::create([
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
            'content'    => "Canal **#{$channel->name}** créé par {$user->name}.",
            'type'       => Message::TYPE_SYSTEM,
        ]);

        $this->newChannelName  = '';
        $this->showNewChannel  = false;
        $this->loadChannels();
        $this->openChannel($channel->id);
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
        $activeChannel = $this->activeChannelId
            ? Channel::with('members:id,name,profile_photo_path')->find($this->activeChannelId)
            : null;

        return view('livewire.provider-company.team-channels', [
            'activeChannel' => $activeChannel,
        ])->layout('layouts.provider-company');
    }
}

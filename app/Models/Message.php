<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Message d'un Channel.
 *
 * Phase 4 :
 *   - Schema réconcilié (user_id + content, plus sender_id + body)
 *   - Threads via parent_id + replies()
 *   - Mentions, attachments, reactions (relations)
 *   - Scope whereSearch() multi-drivers (MySQL FULLTEXT / PG tsvector / SQLite LIKE)
 *
 * Les deux relations ci-dessous reposent sur des clés étrangères NULLABLES (`user_id` en
 * `nullOnDelete`, `parent_id` sur un message supprimable) : elles rendent `null` en pratique, ce
 * que le type générique `BelongsTo<User>` ne dit pas.
 *
 * @property-read User|null $sender
 * @property-read Message|null $parent
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_FILE = 'file';

    /**
     * Une note vocale : le son vit dans une pièce jointe, la durée dans les métadonnées.
     *
     * Le type était écrit en dur (`'voice'`) au point d'envoi, sans constante : rien ne permettait
     * de le reconnaître ailleurs, et la sérialisation ne le transmettait pas du tout — on pouvait
     * envoyer une note que personne ne pouvait écouter.
     */
    public const TYPE_VOICE = 'voice';

    public const TYPE_TASK = 'task';

    public const TYPE_MISSION_UPDATE = 'mission_update';

    protected $fillable = [
        'channel_id',
        'user_id',
        'content',
        'type',
        'parent_id',
        'metadata',
        'edited_at',
        'replies_count',
        'last_reply_at',

        // Moderation
        'deleted_by',
        'deleted_reason',
        'is_pinned',
        'pinned_at',
        'pinned_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'edited_at' => 'datetime',
        'last_reply_at' => 'datetime',
        'replies_count' => 'integer',

        // Moderation
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * L'expéditeur peut être nul.
     *
     * `messages.user_id` est nullable et déclaré `nullOnDelete()` : supprimer un compte laisse ses
     * messages en place, sans expéditeur. Tout affichage doit donc prévoir ce cas — d'où le repli
     * « Utilisateur supprimé » côté composant.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('created_at');
    }

    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /** @return HasMany<MessageMention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * UNE COLONNE ET UNE RELATION PORTAIENT LE MÊME NOM — LA COLONNE GAGNAIT (corrigé le 2026-08-05).
     *
     * La table `messages` porte une colonne JSON `attachments` (héritée, jamais écrite : aucune
     * ligne du dépôt n'y insère quoi que ce soit) EN PLUS de la relation `attachments()`
     * ci-dessus. Or Eloquent résout `$message->attachments` en consultant d'abord les colonnes :
     * l'eager-load `->with('attachments')` chargeait bien la relation, puis l'accès rendait la
     * colonne — c'est-à-dire `null`.
     *
     * Conséquence : `TeamChannels::loadMessages()` faisait « Call to a member function map() on
     * null » dès qu'un seul message existait. Afficher une conversation était impossible.
     *
     * Cet accesseur rend la priorité à la relation, pour TOUS les appelants et sans migration
     * destructive sur une colonne qui pourrait encore contenir des données en production.
     * `getRelationValue()` renvoie la relation déjà chargée si elle l'est, sinon l'exécute — il
     * ne repasse pas par cet accesseur, donc pas de récursion.
     *
     * @return Collection<int, MessageAttachment>
     */
    public function getAttachmentsAttribute(): Collection
    {
        return $this->getRelationValue('attachments');
    }

    /**
     * @return HasMany<MessageRead, $this>
     */
    public function readBy(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    /** Top-level messages only (no thread replies). */
    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    /**
     * Recherche full-text adaptée au driver DB.
     * MySQL  → MATCH(content) AGAINST (?)
     * PG     → to_tsvector(content) @@ plainto_tsquery(?)
     * SQLite → content LIKE %term%
     */
    public function scopeWhereSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return $q->whereRaw('MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$term]);
        }

        if ($driver === 'pgsql') {
            return $q->whereRaw(
                "to_tsvector('simple', coalesce(content,'')) @@ plainto_tsquery('simple', ?)",
                [$term]
            );
        }

        // SQLite (tests) ou fallback
        return $q->where('content', 'like', '%'.$term.'%');
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }

    public function isThreadReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Incrémente le compteur de replies + last_reply_at sur le parent
     * (appelé par MessageObserver après création d'une reply).
     */
    public function refreshThreadStats(): void
    {
        $latest = $this->replies()->latest()->first();
        $this->replies_count = $this->replies()->count();
        $this->last_reply_at = $latest?->created_at;
        $this->saveQuietly();
    }
}

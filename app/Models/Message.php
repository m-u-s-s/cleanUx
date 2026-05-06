<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 */
class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_TEXT          = 'text';
    public const TYPE_SYSTEM        = 'system';
    public const TYPE_FILE          = 'file';
    public const TYPE_TASK          = 'task';
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
    ];

    protected $casts = [
        'metadata'      => 'array',
        'edited_at'     => 'datetime',
        'last_reply_at' => 'datetime',
        'replies_count' => 'integer',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('created_at');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
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
        return $q->where('content', 'like', '%' . $term . '%');
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

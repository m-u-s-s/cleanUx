<?php

namespace App\Models;

use App\Enums\AssistantContextRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation entre un utilisateur et l'assistant LLM.
 *
 * Phase 5 — model créé pour combler le manque (la migration
 * assistant_and_audit_tables crée la table mais ce model n'existait pas
 * → AssistantWidget plantait au runtime).
 */
class AssistantConversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN     = 'open';
    public const STATUS_CLOSED   = 'closed';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id',
        'organization_account_id',
        'context_role',
        'status',
        'context_snapshot',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssistantAction::class);
    }

    public function getContextRoleEnumAttribute(): ?AssistantContextRole
    {
        return $this->context_role
            ? AssistantContextRole::tryFrom($this->context_role)
            : null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function close(): void
    {
        $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }
}

<?php

namespace App\Models;

use App\Enums\AssistantContextRole;
use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversation entre un utilisateur et l'assistant LLM. */
class AssistantConversation extends Model
{
    /** @use HasFactory<AssistantConversationFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }

    /** @return HasMany<AssistantAction, $this> */
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

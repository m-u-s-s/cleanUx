<?php

namespace App\Models;

use Database\Factories\ContractSlaEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSlaEvent extends Model
{
    /** @use HasFactory<ContractSlaEventFactory> */
    use HasFactory;

    public const KIND_RESPONSE = 'response';

    public const KIND_RESOLUTION = 'resolution';

    public const STATUS_PENDING = 'pending';

    public const STATUS_MET = 'met';

    public const STATUS_BREACHED = 'breached';

    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'mission_id',
        'organization_contract_id',
        'kind',
        'due_at',
        'breached_at',
        'escalated_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'breached_at' => 'datetime',
        'escalated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<OrganizationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OrganizationContract::class, 'organization_contract_id');
    }
}

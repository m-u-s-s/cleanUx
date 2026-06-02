<?php

namespace App\Models;

use Database\Factories\MissionReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReport extends Model
{
    /** @use HasFactory<MissionReportFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'generated_by_user_id',
        'report_number',
        'status',
        'generated_at',
        'summary',
        'checklist_completion_rate',
        'before_photos_count',
        'after_photos_count',
        'incident_count',
        'client_validation',
        'quality_score',
        'report_payload',
        'pdf_path',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'report_payload' => 'array',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}

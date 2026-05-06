<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Pièce jointe à un message.
 *
 * Stockage : par défaut sur disk 'public' (configurable par instance).
 * Anti-malware : statut pending → clean / infected / error.
 * URLs signées (signed routes) pour les fichiers privés.
 */
class MessageAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const AV_STATUS_PENDING  = 'pending';
    public const AV_STATUS_CLEAN    = 'clean';
    public const AV_STATUS_INFECTED = 'infected';
    public const AV_STATUS_ERROR    = 'error';

    protected $fillable = [
        'message_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'image_width',
        'image_height',
        'thumbnail_path',
        'av_status',
        'av_engine',
        'av_scanned_at',
        'av_details',
        'metadata',
    ];

    protected $casts = [
        'size_bytes'    => 'integer',
        'image_width'   => 'integer',
        'image_height'  => 'integer',
        'av_scanned_at' => 'datetime',
        'metadata'      => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeClean(Builder $q): Builder
    {
        return $q->where('av_status', self::AV_STATUS_CLEAN);
    }

    public function scopeReady(Builder $q): Builder
    {
        // En l'absence d'AV configuré, on accepte aussi pending (legacy)
        return $q->whereIn('av_status', [self::AV_STATUS_CLEAN, self::AV_STATUS_PENDING]);
    }

    public function isImage(): bool
    {
        return $this->mime_type !== null
            && str_starts_with($this->mime_type, 'image/');
    }

    public function isInfected(): bool
    {
        return $this->av_status === self::AV_STATUS_INFECTED;
    }

    public function isReady(): bool
    {
        return $this->av_status === self::AV_STATUS_CLEAN
            || (config('messaging.av.required', false) === false && $this->av_status === self::AV_STATUS_PENDING);
    }

    /**
     * URL signée temporaire pour téléchargement.
     * Empêche les URLs Storage::url() publiques sans expiration.
     */
    public function getSignedUrlAttribute(): ?string
    {
        if ($this->isInfected()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'messaging.attachments.download',
            now()->addMinutes(15),
            ['attachment' => $this->id]
        );
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path || $this->isInfected()) {
            return null;
        }
        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

    public function getHumanSizeAttribute(): string
    {
        $size = (int) $this->size_bytes;
        if ($size < 1024) return $size . ' B';
        if ($size < 1024 * 1024) return number_format($size / 1024, 1) . ' KB';
        if ($size < 1024 * 1024 * 1024) return number_format($size / 1024 / 1024, 1) . ' MB';
        return number_format($size / 1024 / 1024 / 1024, 2) . ' GB';
    }

    /**
     * Supprime aussi le fichier physique sur le disk.
     */
    protected static function booted(): void
    {
        static::deleting(function (MessageAttachment $att) {
            if ($att->isForceDeleting()) {
                Storage::disk($att->disk)->delete([
                    $att->path,
                    ...($att->thumbnail_path ? [$att->thumbnail_path] : []),
                ]);
            }
        });
    }
}

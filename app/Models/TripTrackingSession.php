<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TripTrackingSession extends Model
{
    use HasFactory;

    public const STATUS_ENROUTE = 'enroute';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_MISSION = 'in_mission';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'booking_id', 'provider_user_id', 'status',
        'is_paused', 'paused_at', 'paused_total_seconds',
        'destination_lat', 'destination_lng', 'geofence_radius_m',
        'start_lat', 'start_lng',
        'points_count', 'total_distance_m', 'current_eta_seconds',
        'last_lat', 'last_lng', 'last_speed_mps',
        'metadata',
        'started_at', 'arrived_at', 'in_mission_at', 'ended_at', 'last_ping_at',
        // Le code en clair n'est jamais stocké : seule son empreinte l'est.
        'presence_code_hash', 'presence_code_expires_at', 'presence_code_attempts',
        'presence_confirmed_at', 'presence_confirmed_by_user_id',
        // Où se trouvait le prestataire au moment du scan, et ce que le contrôle en a conclu.
        'presence_confirmed_lat', 'presence_confirmed_lng', 'presence_confirmed_accuracy_m',
        'presence_confirmed_distance_m', 'presence_geo_verdict',
    ];

    protected $casts = [
        'destination_lat' => 'float',
        'destination_lng' => 'float',
        'start_lat' => 'float',
        'start_lng' => 'float',
        'last_lat' => 'float',
        'last_lng' => 'float',
        'last_speed_mps' => 'float',
        'is_paused' => 'boolean',
        'paused_at' => 'datetime',
        'paused_total_seconds' => 'integer',
        'geofence_radius_m' => 'integer',
        'points_count' => 'integer',
        'total_distance_m' => 'integer',
        'current_eta_seconds' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'arrived_at' => 'datetime',
        'in_mission_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_ping_at' => 'datetime',
        'presence_code_expires_at' => 'datetime',
        'presence_code_attempts' => 'integer',
        'presence_confirmed_at' => 'datetime',
        'presence_confirmed_lat' => 'float',
        'presence_confirmed_lng' => 'float',
        'presence_confirmed_accuracy_m' => 'float',
        'presence_confirmed_distance_m' => 'integer',
    ];

    /**
     * L'empreinte du code ne doit jamais quitter le serveur : `$hidden` la retire des
     * sérialisations, y compris celles qu'on n'a pas écrites soi-même.
     */
    protected $hidden = ['presence_code_hash'];

    public static function generateCode(): string
    {
        return 'trip_'.Str::lower(Str::random(24));
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    /** @return HasMany<TripTrackingPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(TripTrackingPoint::class, 'session_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_ENROUTE,
            self::STATUS_ARRIVED,
            self::STATUS_IN_MISSION,
        ], true);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_ENROUTE,
            self::STATUS_ARRIVED,
            self::STATUS_IN_MISSION,
        ]);
    }

    /**
     * LE TEMPS RÉELLEMENT TRAVAILLÉ, pauses déduites (F4).
     *
     * C'est cette valeur — pas la durée de présence — que consommeront les feuilles d'heures et le
     * calcul de rentabilité. Sur une intervention de quatre heures dont une de déjeuner, les
     * confondre fait payer ou facturer une heure de trop.
     *
     * Le compte part de l'entrée en mission, jamais du départ : le trajet n'est pas du travail sur
     * place. Une session qui n'a pas encore commencé rend zéro plutôt que nul — « rien de
     * travaillé » est une réponse, pas une absence de réponse.
     */
    public function workedSeconds(): int
    {
        if (! $this->in_mission_at) {
            return 0;
        }

        $fin = $this->ended_at ?? now();

        // `abs()` : une horloge d'appareil en avance rendrait un négatif, et un temps travaillé
        // négatif se propagerait jusqu'à une fiche de paie.
        $presence = (int) abs($fin->diffInSeconds($this->in_mission_at));

        // La pause en cours compte : sans elle, consulter la durée pendant une pause l'annoncerait
        // trop haute, et la valeur bougerait à la reprise sans que rien ne se soit passé.
        $pauses = (int) $this->paused_total_seconds;

        if ($this->is_paused && $this->paused_at) {
            $pauses += (int) abs(now()->diffInSeconds($this->paused_at));
        }

        return max(0, $presence - $pauses);
    }

    /** La durée travaillée en minutes, arrondie — l'unité des feuilles d'heures. */
    public function workedMinutes(): int
    {
        return (int) round($this->workedSeconds() / 60);
    }
}

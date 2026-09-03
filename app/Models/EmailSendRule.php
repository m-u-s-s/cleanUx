<?php

namespace App\Models;

use Database\Factories\EmailSendRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * QUAND UN GABARIT PART, ET COMBIEN DE FOIS.
 *
 * Un gabarit peut porter plusieurs règles : un rappel la veille et un autre deux heures avant
 * sont deux règles, pas deux gabarits.
 *
 * @property ?Carbon $last_ran_at
 */
class EmailSendRule extends Model
{
    /** @use HasFactory<EmailSendRuleFactory> */
    use HasFactory;

    public const TYPES = ['manual', 'event', 'schedule', 'reminder'];

    public const FREQUENCES = ['daily', 'weekly', 'monthly'];

    protected $fillable = [
        'email_template_id', 'name', 'is_active',
        'trigger_type', 'trigger_key', 'offset_minutes',
        'frequency', 'hour', 'weekday', 'monthday',
        'cap_per_recipient', 'cap_window_hours', 'respects_opt_out',
        'last_ran_at', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'respects_opt_out' => 'boolean',
        'offset_minutes' => 'integer',
        'hour' => 'integer',
        'weekday' => 'integer',
        'monthday' => 'integer',
        'cap_per_recipient' => 'integer',
        'cap_window_hours' => 'integer',
        'last_ran_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<EmailTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Comment se lit ce déclencheur, en une phrase. */
    public function enUnePhrase(): string
    {
        return match ($this->trigger_type) {
            'event' => 'À l’événement « '.($this->trigger_key ?: 'non défini').' »',
            'schedule' => match ($this->frequency) {
                'daily' => 'Chaque jour à '.$this->heureLisible(),
                'weekly' => 'Chaque '.$this->jourLisible().' à '.$this->heureLisible(),
                'monthly' => 'Le '.($this->monthday ?: 1).' de chaque mois à '.$this->heureLisible(),
                default => 'Fréquence non définie',
            },
            'reminder' => $this->rappelLisible(),
            default => 'Envoi manuel',
        };
    }

    private function rappelLisible(): string
    {
        $jalon = $this->trigger_key ?: 'un jalon';
        $minutes = abs($this->offset_minutes);
        $duree = $minutes >= 1440
            ? intdiv($minutes, 1440).' jour(s)'
            : ($minutes >= 60 ? intdiv($minutes, 60).' heure(s)' : $minutes.' minute(s)');

        // LE SIGNE PORTE L'INTENTION : négatif se lit « avant », positif « après ».
        return $this->offset_minutes < 0
            ? $duree.' avant « '.$jalon.' »'
            : $duree.' après « '.$jalon.' »';
    }

    private function heureLisible(): string
    {
        return str_pad((string) $this->hour, 2, '0', STR_PAD_LEFT).'h';
    }

    private function jourLisible(): string
    {
        return [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
            5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'][$this->weekday] ?? 'lundi';
    }
}

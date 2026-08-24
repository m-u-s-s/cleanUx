<?php

namespace App\Models\Concerns;

/** HasLegacyBookingAliases Handles synchronisation between legacy French field names and their modern English equivalents. */
trait HasLegacyBookingAliases
{
    /** Mapping of legacy French columns → modern English equivalents. */
    // `surface` et `surface_m2` NE SONT PAS une paire, malgré leurs noms.
    protected static array $legacyAliasPairs = [
        ['client_id',               'customer_user_id'],
        ['employe_id',              'assigned_provider_user_id'],
        ['organization_account_id', 'customer_organization_id'],
        ['date',                    'scheduled_date'],
        ['heure',                   'scheduled_time'],
        ['adresse',                 'address'],
        ['ville',                   'city'],
        ['code_postal',             'postal_code'],
        ['frequence',               'frequency'],
        ['priorite',                'priority'],
        ['commentaire_client',      'customer_comment'],
        ['telephone_client',        'contact_phone'],
        ['devis_estime',            'estimated_price'],
        ['duree_estimee',           'estimated_duration_minutes'],
    ];

    /** Format attendu par les colonnes date/heure qui n'ont PAS de cast de modèle. */
    protected static array $legacyAliasDateFormats = [
        'heure' => 'H:i:s',
        'date' => 'Y-m-d',
    ];

    /**
     * VOCABULAIRE ATTENDU PAR CHAQUE CÔTÉ D'UNE PAIRE ÉNUMÉRÉE.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $legacyAliasValueMaps = [
        'priorite' => ['low' => 'basse', 'normal' => 'normale', 'high' => 'haute', 'urgent' => 'urgente'],
        'priority' => ['basse' => 'low', 'normale' => 'normal', 'haute' => 'high', 'urgente' => 'urgent'],
    ];

    /** Synchronise legacy FR ↔ modern EN so that both columns are always consistent in the database regardless of which side was written. */
    public function syncLegacyAliases(): void
    {
        /**
         * UN SEUL `getDirty()` PAR ENREGISTREMENT, AU LIEU DE TRENTE.
         *
         * @var array<string, mixed> $modifiees
         */
        $modifiees = $this->getDirty();

        foreach (static::$legacyAliasPairs as [$legacy, $modern]) {
            $this->propagerLaPaire($legacy, $modern, $modifiees);
        }

        $this->fillScheduledAt($this->getDirty());
    }

    /** COMBLER UN TROU NE SUFFIT PAS : IL FAUT AUSSI PROPAGER UN CHANGEMENT. */
    /**
     * @param  array<string, mixed>  $modifiees  Le relevé de `getDirty()`, pris UNE fois par enregistrement.
     */
    protected function propagerLaPaire(string $legacy, string $modern, array $modifiees): void
    {
        // Un trou se comble toujours — c'est le comportement d'origine, et il reste le premier.
        if (blank($this->{$legacy}) && filled($this->{$modern})) {
            $this->{$legacy} = $this->normaliseLegacyAliasValue($legacy, $this->{$modern});

            return;
        }

        if (blank($this->{$modern}) && filled($this->{$legacy})) {
            $this->{$modern} = $this->normaliseLegacyAliasValue($modern, $this->{$legacy});

            return;
        }

        if (blank($this->{$legacy}) || blank($this->{$modern})) {
            return;
        }

        $legacyModifiee = array_key_exists($legacy, $modifiees);
        $moderneModifiee = array_key_exists($modern, $modifiees);

        if ($legacyModifiee === $moderneModifiee) {
            return;
        }

        if ($legacyModifiee) {
            $this->{$modern} = $this->normaliseLegacyAliasValue($modern, $this->{$legacy});
        } else {
            $this->{$legacy} = $this->normaliseLegacyAliasValue($legacy, $this->{$modern});
        }
    }

    /** Reconstitue l'horodatage complet du rendez-vous à partir du jour et de l'heure. */
    /**
     * @param  array<string, mixed>  $modifiees  Le même relevé que `propagerLaPaire()`, pour la même raison.
     */
    protected function fillScheduledAt(array $modifiees): void
    {
        if (blank($this->date) || blank($this->heure)) {
            return;
        }

        // L'APPELANT QUI ÉCRIT L'HORODATAGE LUI-MÊME A RAISON.
        if (array_key_exists('scheduled_at', $modifiees)) {
            return;
        }

        // SINON ON RECALCULE DÈS QUE LE CRÉNEAU BOUGE.
        if (filled($this->scheduled_at) && ! array_key_exists('date', $modifiees) && ! array_key_exists('heure', $modifiees)) {
            return;
        }

        // `date` est casté : l'accès rend un Carbon à minuit. `heure` n'a pas de cast et reste
        // une chaîne « HH:MM:SS ». On repart donc du jour, auquel on applique l'heure.
        $heure = substr((string) $this->heure, 0, 8);

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $heure, $parties)) {
            // Heure illisible : on laisse la colonne nulle plutôt que d'inventer un horaire. Le
            // moteur retombe alors sur son repli, ce qui est moins faux qu'une heure fabriquée.
            return;
        }

        $this->scheduled_at = $this->date
            ->copy()
            ->setTime((int) $parties[1], (int) $parties[2], (int) ($parties[3] ?? 0));
    }

    /** Rend la valeur sous la forme attendue par l'attribut de destination. */
    protected function normaliseLegacyAliasValue(string $attribute, mixed $value): mixed
    {
        if (is_string($value) && isset(static::$legacyAliasValueMaps[$attribute][$value])) {
            return static::$legacyAliasValueMaps[$attribute][$value];
        }

        if (! $value instanceof \DateTimeInterface || $this->hasCast($attribute)) {
            return $value;
        }

        return $value->format(static::$legacyAliasDateFormats[$attribute] ?? 'Y-m-d H:i:s');
    }
}

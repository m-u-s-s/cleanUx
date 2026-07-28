<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HasLegacyBookingAliases
 *
 * Handles synchronisation between legacy French field names and their modern
 * English equivalents, plus the mirror-write to the legacy `rendez_vous` table.
 *
 * Extracted from Booking (was inline) to keep the model under 400 lines.
 */
trait HasLegacyBookingAliases
{
    /**
     * Mapping of legacy French columns → modern English equivalents.
     * Each pair is kept in sync bidirectionally on every save.
     */
    protected static array $legacyAliasPairs = [
        ['client_id',               'customer_user_id'],
        ['employe_id',              'assigned_provider_user_id'],
        ['organization_account_id', 'customer_organization_id'],
        ['date',                    'scheduled_date'],
        ['heure',                   'scheduled_time'],
        ['adresse',                 'address'],
        ['ville',                   'city'],
        ['code_postal',             'postal_code'],
        ['type_lieu',               'place_type'],
        ['surface',                 'surface_m2'],
        ['frequence',               'frequency'],
        ['priorite',                'priority'],
        ['commentaire_client',      'customer_comment'],
        ['telephone_client',        'contact_phone'],
        ['devis_estime',            'estimated_price'],
        ['duree_estimee',           'estimated_duration_minutes'],
    ];

    /**
     * Format attendu par les colonnes date/heure qui n'ont PAS de cast de modèle.
     *
     * `scheduled_time` est casté `datetime:H:i` et rend donc un Carbon. Recopié tel quel dans
     * `heure` — colonne TIME sans cast — il y laissait un Carbon là où tout le code appelant
     * attend "10:00:00" et pratique `substr((string) $heure, 0, 5|8)`. Ce découpage rendait
     * alors "2026-07-", d'où un datetime ininterprétable : MissionFromRendezVousSyncService en
     * tirait planned_start_at = 1970-01-01 00:00:00, valeur qu'une colonne TIMESTAMP MySQL
     * refuse en mode strict. Le chemin de réservation ASAP échouait donc en production.
     */
    protected static array $legacyAliasDateFormats = [
        'heure' => 'H:i:s',
        'date' => 'Y-m-d',
    ];

    /**
     * Synchronise legacy FR ↔ modern EN so that both columns are always
     * consistent in the database regardless of which side was written.
     */
    public function syncLegacyAliases(): void
    {
        foreach (static::$legacyAliasPairs as [$legacy, $modern]) {
            if (blank($this->{$legacy}) && filled($this->{$modern})) {
                $this->{$legacy} = $this->normaliseLegacyAliasValue($legacy, $this->{$modern});
            }
            if (blank($this->{$modern}) && filled($this->{$legacy})) {
                $this->{$modern} = $this->normaliseLegacyAliasValue($modern, $this->{$legacy});
            }
        }
    }

    /**
     * Rend la valeur sous la forme attendue par l'attribut de destination.
     *
     * Un attribut qui possède son propre cast sait se reformater : on le laisse faire. Seuls
     * les alias sans cast ont besoin qu'on remette la date ou l'heure en chaîne.
     */
    protected function normaliseLegacyAliasValue(string $attribute, mixed $value): mixed
    {
        if (! $value instanceof \DateTimeInterface || $this->hasCast($attribute)) {
            return $value;
        }

        return $value->format(static::$legacyAliasDateFormats[$attribute] ?? 'Y-m-d H:i:s');
    }

    /**
     * Replicates this record into the legacy `rendez_vous` table.
     * Silent no-op when the table does not exist (e.g. fresh installs).
     */
    public function mirrorIntoLegacyRendezVousTable(): void
    {
        if (! Schema::hasTable('rendez_vous')) {
            return;
        }

        $cachedColumns = Schema::getColumnListing('rendez_vous');

        $candidate = [
            'id' => $this->id,
            'booking_reference' => $this->booking_reference,
            'client_id' => $this->client_id,
            'employe_id' => $this->employe_id,
            'user_id' => $this->client_id,
            'service_catalog_id' => $this->service_catalog_id,
            'service_zone_id' => $this->service_zone_id,
            'postal_code_id' => $this->postal_code_id,
            'status' => $this->status,
            'date' => $this->date,
            'heure' => $this->heure,
            'scheduled_at' => $this->scheduled_at,
            'adresse' => $this->adresse,
            'address' => $this->adresse,
            'ville' => $this->ville,
            'city' => $this->ville,
            'code_postal' => $this->code_postal,
            'postal_code' => $this->code_postal,
            'zone_snapshot' => is_array($this->zone_snapshot) ? json_encode($this->zone_snapshot) : $this->zone_snapshot,
            'pricing_snapshot' => is_array($this->pricing_snapshot) ? json_encode($this->pricing_snapshot) : $this->pricing_snapshot,
            'estimated_price' => $this->estimated_price ?? $this->devis_estime,
            'final_price' => $this->final_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $payload = collect($candidate)
            ->filter(fn ($value, $key) => in_array($key, $cachedColumns, true))
            ->all();

        if (empty($payload['id'])) {
            return;
        }

        try {
            DB::table('rendez_vous')->updateOrInsert(
                ['id' => $payload['id']],
                $payload,
            );
        } catch (\Throwable) {
            // Silently ignore — the table may have stricter FK constraints
            // in certain environments, and the mirror is best-effort only.
        }
    }
}

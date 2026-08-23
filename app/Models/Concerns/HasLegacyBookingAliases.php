<?php

namespace App\Models\Concerns;

/**
 * HasLegacyBookingAliases
 *
 * Handles synchronisation between legacy French field names and their modern
 * English equivalents.
 *
 * LA RECOPIE VERS `rendez_vous` A ÉTÉ RETIRÉE. Elle dupliquait chaque réservation dans une table
 * jumelle à chaque enregistrement, en avalant ses propres erreurs : un échec ne laissait aucune
 * trace, et la plateforme décidait ensuite sur cette copie — l'affectation comptait les missions du
 * jour dessus. Une copie dont on ignore si elle est à jour est pire qu'une absence de copie.
 *
 * Extracted from Booking (was inline) to keep the model under 400 lines.
 */
trait HasLegacyBookingAliases
{
    /**
     * Mapping of legacy French columns → modern English equivalents.
     * Each pair is kept in sync bidirectionally on every save.
     */
    /*
     * `surface` et `surface_m2` NE SONT PAS une paire, malgré leurs noms.
     *
     * `surface` pointe sur `surface_range`, un LIBELLÉ DE PLAGE choisi par le client
     * (« 100_150 »). `surface_m2` est un entier : validé `numeric`, casté `integer`, moyenné dans
     * les exports et comparé par le moteur de prix (« surface_m2 >= 50 »).
     *
     * Les recopier l'un dans l'autre écrivait « 100_150 » dans une colonne `int unsigned` :
     * refusé par MySQL en mode strict — donc toute réservation portant une plage échouait en
     * production — et, quand ça passait, `(int) '100_150'` valait 100, si bien que le moteur de
     * prix traitait une fourchette comme une valeur exacte. Convertir une plage en nombre
     * inventerait une précision que le client n'a jamais donnée : la paire est retirée, pas
     * corrigée.
     *
     * Même classe de défaut que `heure`/`date` ci-dessous, invisible pour la même raison : SQLite
     * accepte n'importe quoi dans n'importe quelle colonne, MySQL non.
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
     * VOCABULAIRE ATTENDU PAR CHAQUE CÔTÉ D'UNE PAIRE ÉNUMÉRÉE.
     *
     * Recopier une date sans la formater cassait le chemin ASAP ; recopier une PRIORITÉ sans la
     * traduire casse le chemin des urgences, et de la même manière — la valeur arrive intacte dans
     * une colonne dont personne n'emploie ce mot.
     *
     * Les deux côtés ne parlent pas la même langue, et chacun a raison chez lui :
     *
     *   `priorite`  les listes de choix qui alimentent les filtres — `normale`, `haute`, `urgente`
     *               (resources/views/livewire/admin/missions/filters.blade.php:36-38 et
     *                resources/views/livewire/employe/mes-rendez-vous.blade.php:8-10). C'est la
     *               colonne que tout le dépôt interroge : 86 emplacements, dont des `where()` qui
     *               court-circuitent tout accesseur de modèle.
     *   `priority`  ce que valide l'API — `in:normal,urgent,low`
     *               (app/Http/Requests/Api/Client/StoreBookingRequest.php:30). AUCUN filtre du
     *               dépôt ne l'interroge sur cette table.
     *
     * Mesuré avant correction : `CreateBookingFromApiAction.php:112` écrit `'urgent'` pour une
     * réservation immédiate, le trait le recopiait tel quel, et les lecteurs cherchent `'urgente'`
     * — SendRendezVousReminders.php:126 (l'alerte d'urgence), PlanningAdmin.php:160/185/229,
     * AgendaHebdomadaire.php:94, ProfilClient.php:95. Une réservation immédiate passée par l'API
     * n'était donc urgente pour personne.
     *
     * La table couvre l'UNION des deux vocabulaires, pas leur intersection : l'API ne sait pas dire
     * `haute` et les écrans ne proposent pas `basse`, mais une valeur venue d'ailleurs ne doit pas
     * se perdre en traversant. Toute valeur absente de la table passe inchangée — ce mécanisme ne
     * peut donc rien réécrire qu'il ne connaisse.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $legacyAliasValueMaps = [
        'priorite' => ['low' => 'basse', 'normal' => 'normale', 'high' => 'haute', 'urgent' => 'urgente'],
        'priority' => ['basse' => 'low', 'normale' => 'normal', 'haute' => 'high', 'urgente' => 'urgent'],
    ];

    /**
     * Synchronise legacy FR ↔ modern EN so that both columns are always
     * consistent in the database regardless of which side was written.
     */
    public function syncLegacyAliases(): void
    {
        /*
         * UN SEUL `getDirty()` PAR ENREGISTREMENT, AU LIEU DE TRENTE.
         *
         * `isDirty($colonne)` n'est pas une lecture : Laravel y appelle `getDirty()`, qui parcourt
         * TOUTES les colonnes du modèle et les compare à leur valeur d'origine
         * (`HasAttributes::isDirty` → `hasChanges($this->getDirty(), [...])`). Sur `bookings` et ses
         * 164 colonnes, chaque appel coûtait donc 164 comparaisons — et cette méthode en faisait
         * trente, soit près de CINQ MILLE comparaisons par enregistrement, pour répondre quinze
         * fois à « cette colonne a-t-elle changé ? ».
         *
         * Mesuré avant correction : `syncLegacyAliases()` prenait 7,97 ms quand un `save()` complet
         * en prenait 10,62 — les trois quarts du coût d'écriture de la table la plus écrite de la
         * plateforme, dépensés à recalculer trente fois la même liste.
         *
         * L'équivalence est exacte, et vérifiée dans le framework : `isDirty($c)` vaut
         * `array_key_exists($c, $this->getDirty())`.
         *
         * ── LE RELEVÉ VAUT POUR LA BOUCLE, PAS AU-DELÀ ───────────────────────────────────────
         *
         * Pendant la boucle il reste juste : les quinze paires portent sur des colonnes DISJOINTES,
         * propager l'une ne change donc jamais l'état des autres.
         *
         * `fillScheduledAt()`, lui, s'exécute APRÈS et doit voir ce que la boucle vient d'écrire.
         * Une reprogrammation écrit `scheduled_date` ; la boucle en déduit `date` ; le relevé
         * initial ignore ce changement, la garde sort trop tôt et `scheduled_at` n'est jamais
         * recalculé — quatre tests de reprogrammation sont tombés là-dessus. Il reçoit donc un
         * relevé FRAIS. Cela fait deux `getDirty()` par enregistrement au lieu de trente, ce qui
         * était tout l'objet.
         *
         * @var array<string, mixed> $modifiees
         */
        $modifiees = $this->getDirty();

        foreach (static::$legacyAliasPairs as [$legacy, $modern]) {
            $this->propagerLaPaire($legacy, $modern, $modifiees);
        }

        $this->fillScheduledAt($this->getDirty());
    }

    /**
     * COMBLER UN TROU NE SUFFIT PAS : IL FAUT AUSSI PROPAGER UN CHANGEMENT.
     *
     * L'ancienne version ne recopiait que dans une colonne VIDE. Tant qu'on ne faisait que créer
     * des réservations, cela ressemblait à une synchronisation — les deux colonnes finissaient
     * toujours d'accord. À la première MODIFICATION, elles divergeaient définitivement : la
     * reprogrammation écrit `scheduled_date` et `scheduled_time`, les deux étaient déjà remplies,
     * donc `date` et `heure` gardaient l'ancien créneau pour toujours.
     *
     * Mesuré : un rendez-vous du 10 septembre 10 h déplacé au 12 septembre 14 h gardait
     * `date = 2026-09-10` et `heure = 10:00:00`. Le moteur d'annulation, l'affectation et le
     * minuteur de retard lisent ce couple — ils décidaient tous sur un créneau abandonné.
     *
     * ── CE QUI DÉPARTAGE ─────────────────────────────────────────────────────────────────────
     *
     * La fraîcheur, et elle seule. Si une seule des deux colonnes a changé dans cet
     * enregistrement, c'est elle qui fait foi. Si les deux ont changé, l'appelant a tranché
     * lui-même et on ne devine pas à sa place. Si aucune n'a changé, il n'y a rien à propager.
     */
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

    /**
     * Reconstitue l'horodatage complet du rendez-vous à partir du jour et de l'heure.
     *
     * `scheduled_at` n'était rempli par AUCUN chemin : la colonne existait, absente de
     * `$fillable`, donc toute écriture était silencieusement ignorée. Le moteur d'annulation la
     * lit pourtant en premier et retombait sur `date` — de type DATE sur MySQL, donc tronquée au
     * jour. Les frais d'annulation se calculaient contre MINUIT et non contre l'heure réelle :
     * un client annulant un rendez-vous de 17 h trente heures à l'avance était facturé au palier
     * « moins de 24 h ».
     *
     * Invisible sur SQLite, qui stocke `date` en texte et y conserve l'heure.
     */
    /**
     * @param  array<string, mixed>  $modifiees  Le même relevé que `propagerLaPaire()`, pour la même raison.
     */
    protected function fillScheduledAt(array $modifiees): void
    {
        if (blank($this->date) || blank($this->heure)) {
            return;
        }

        /*
         * L'APPELANT QUI ÉCRIT L'HORODATAGE LUI-MÊME A RAISON. On ne le recalcule jamais par-dessus.
         */
        if (array_key_exists('scheduled_at', $modifiees)) {
            return;
        }

        /*
         * SINON ON RECALCULE DÈS QUE LE CRÉNEAU BOUGE. La version d'origine s'arrêtait à
         * « déjà rempli », ce qui la rendait muette après toute reprogrammation : l'horodatage
         * gardait l'heure d'avant, et le barème d'annulation facturait contre elle.
         */
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

    /**
     * Rend la valeur sous la forme attendue par l'attribut de destination.
     *
     * Un attribut qui possède son propre cast sait se reformater : on le laisse faire. Seuls
     * les alias sans cast ont besoin qu'on remette la date ou l'heure en chaîne.
     */
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

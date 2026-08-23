<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE MOT « URGENT » N'ARRIVE PAS JUSQU'AUX FILTRES QUI LE CHERCHENT.
 *
 * `bookings` porte deux colonnes pour une seule notion de priorité, et elles ne parlent pas la
 * même langue :
 *
 *   `priorite` — la colonne que TOUTE la plateforme interroge. Ses valeurs viennent des listes
 *                de choix : `normale`, `haute`, `urgente`
 *                (resources/views/livewire/admin/missions/filters.blade.php:36-38,
 *                 resources/views/livewire/employe/mes-rendez-vous.blade.php:8-10).
 *   `priority` — la colonne que l'API renseigne, validée `in:normal,urgent,low`
 *                (app/Http/Requests/Api/Client/StoreBookingRequest.php:30). AUCUN filtre du dépôt
 *                ne l'interroge sur cette table.
 *
 * `HasLegacyBookingAliases::propagerLaPaire()` recopie l'une dans l'autre SANS traduire :
 * `normaliseLegacyAliasValue()` ne traite que les dates et rend toute autre valeur telle quelle.
 *
 * ── LA CONSÉQUENCE, ET ELLE PORTE SUR LE CHEMIN LE PLUS PRESSÉ ───────────────────────────────
 *
 * `CreateBookingFromApiAction.php:112` écrit `'urgent'` pour une réservation immédiate. Le trait
 * la recopie telle quelle dans `priorite`. Or ces lecteurs cherchent `'urgente'` :
 *
 *   SendRendezVousReminders.php:126   l'alerte d'urgence — elle n'est jamais envoyée
 *   PlanningAdmin.php:160, 185, 229   le compte et la section « urgentes » du planning
 *   AgendaHebdomadaire.php:94         le compte d'urgences du jour
 *   ProfilClient.php:95               le compte d'urgences du client
 *
 * Autrement dit : une réservation immédiate passée par l'API n'est urgente pour personne.
 *
 * ── LE TÉMOIN ────────────────────────────────────────────────────────────────────────────────
 *
 * Le premier test n'est pas décoratif. Sans lui, « la réservation n'est pas trouvée » passerait au
 * vert même si la requête était cassée pour une tout autre raison : on mesurerait une panne au
 * lieu de mesurer le défaut.
 */
class PrioriteVocabulaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_temoin_le_filtre_des_urgences_trouve_une_reservation_ecrite_en_francais(): void
    {
        Booking::factory()->create(['priorite' => 'urgente']);

        $this->assertSame(
            1,
            Booking::query()->where('priorite', 'urgente')->count(),
            'Le témoin échoue : le filtre lui-même est cassé, aucun autre test de ce fichier ne prouverait quoi que ce soit.'
        );
    }

    public function test_une_priorite_ecrite_en_anglais_arrive_en_francais_dans_la_colonne_lue(): void
    {
        $reservation = Booking::factory()->create(['priorite' => null, 'priority' => 'urgent']);

        $this->assertSame('urgente', $reservation->fresh()->priorite);
        $this->assertSame(
            1,
            Booking::query()->where('priorite', 'urgente')->count(),
            "Une réservation immédiate créée par l'API doit compter parmi les urgences."
        );
    }

    public function test_une_priorite_ecrite_en_francais_arrive_en_anglais_dans_la_colonne_de_l_api(): void
    {
        $reservation = Booking::factory()->create(['priorite' => 'urgente']);

        $this->assertSame('urgent', $reservation->fresh()->priority);
    }

    public function test_les_deux_colonnes_ne_se_contredisent_jamais(): void
    {
        // Les trois points de depart releves ensemble : un vocabulaire desaccorde l'est
        // generalement dans les deux sens, et savoir que le premier decroche ne dit rien des autres.
        $ecarts = [];

        foreach ([['priorite' => 'normale'], ['priorite' => null, 'priority' => 'normal'], ['priorite' => 'haute']] as $i => $depart) {
            $reservation = Booking::factory()->create($depart)->fresh();
            $attendu = $this->versLAnglais($reservation->priorite);

            if ($reservation->priority !== $attendu) {
                $ecarts[] = sprintf(
                    'depart #%d : priorite « %s » donne priority « %s », attendu « %s »',
                    $i, $reservation->priorite, $reservation->priority, $attendu,
                );
            }
        }

        $this->assertSame([], $ecarts, 'Les deux colonnes decrivent la meme priorite : elles doivent rester traduisibles l une dans l autre.');
    }

    private function versLAnglais(?string $francais): ?string
    {
        return match ($francais) {
            'basse' => 'low',
            'normale' => 'normal',
            'haute' => 'high',
            'urgente' => 'urgent',
            default => $francais,
        };
    }
}

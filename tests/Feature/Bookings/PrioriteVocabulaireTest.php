<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LE MOT « URGENT » N'ARRIVE PAS JUSQU'AUX FILTRES QUI LE CHERCHENT. */
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

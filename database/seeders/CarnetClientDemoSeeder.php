<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ClientPlace;
use App\Models\User;
use App\Services\Client\ClientPlaceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** LE CARNET DE LIEUX (E2) ET LE BÉNÉFICIAIRE (E1), SUR LA BASE DE DÉMONSTRATION. */
class CarnetClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = DB::table('bookings')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->value('client_id');

        $client = $clientId ? User::find($clientId) : null;

        if (! $client) {
            $this->command?->warn('⚠️ Aucun client avec réservation : carnet de lieux ignoré.');

            return;
        }

        $service = app(ClientPlaceService::class);

        $definitions = [
            [
                'label' => 'Chez moi',
                'address' => 'Rue Haute 42',
                'city' => 'Bruxelles',
                'postal_code' => '1000',
                'floor' => '3e étage, porte gauche',
                'access_instructions' => 'Digicode 4512B. La clé est chez la voisine du 2e si absente.',
                'alarm_code_required' => true,
                'preferences' => [
                    'products' => 'Produits sans chlore uniquement.',
                    'allergies' => 'Allergie aux parfums d’agrumes.',
                    'pets' => 'Un chat, qui se cache.',
                ],
            ],
            [
                'label' => 'Maison de maman',
                'address' => 'Chaussée de Wavre 200',
                'city' => 'Ixelles',
                'postal_code' => '1050',
                'floor' => 'Rez-de-chaussée',
                'access_instructions' => 'Sonner longtemps, elle met du temps à venir.',
            ],
        ];

        $premier = null;

        foreach ($definitions as $definition) {
            $existant = ClientPlace::query()
                ->where('user_id', $client->id)
                ->where('label', $definition['label'])
                ->first();

            $lieu = $existant ?? $service->enregistrer($client, $definition);

            $premier ??= $lieu;
        }

        // UNE RÉSERVATION RATTACHÉE AU LIEU, ET AVEC UN BÉNÉFICIAIRE.
        $reservation = Booking::query()
            ->where('client_id', $client->id)
            ->whereNull('client_place_id')
            ->orderByDesc('id')
            ->first();

        if ($reservation && $premier) {
            $reservation->forceFill([
                'client_place_id' => $premier->id,
                'beneficiary_name' => 'Madame Renard',
                'beneficiary_phone' => '+32470112233',
                'beneficiary_note' => 'Ma mère a 82 ans, sonnez longtemps.',
            ])->save();
        }

        $this->command?->info('✅ Carnet de lieux : 2 lieux avec consignes, et une intervention pour un proche.');
    }
}

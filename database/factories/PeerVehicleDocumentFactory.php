<?php

namespace Database\Factories;

use App\Models\PeerVehicle;
use App\Models\PeerVehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerVehicleDocument> */
class PeerVehicleDocumentFactory extends Factory
{
    protected $model = PeerVehicleDocument::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_vehicle_id' => PeerVehicle::factory(),
            'document_type' => PeerVehicleDocument::TYPE_CARTE_GRISE,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => 'peer-documents/'.$this->faker->uuid().'.pdf',
            'file_name' => 'carte-grise.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120000,
        ];
    }

    public function valide(): static
    {
        return $this->state(fn (array $attributs): array => [
            'status' => PeerVehicleDocument::STATUT_VALIDE,
            'reviewed_at' => now(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    }
}

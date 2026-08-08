<?php

namespace Database\Factories;

use App\Models\FinanceCreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinanceCreditNote> */
class FinanceCreditNoteFactory extends Factory
{
    protected $model = FinanceCreditNote::class;

    public function definition(): array
    {
        return [
            // Le numéro de note de crédit est une pièce comptable : il doit rester unique.
            'credit_note_number' => 'NC-'.date('Y').'-'.fake()->unique()->numerify('######'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\AcademyCourse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AcademyCourse>
 */
class AcademyCourseFactory extends Factory
{
    protected $model = AcademyCourse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'course-'.Str::lower(Str::random(8)),
            'title' => 'Produits et surfaces : ne pas abîmer',
            'summary' => 'Quinze minutes pour éviter les trois erreurs les plus coûteuses.',
            'duration_minutes' => 15,
            'specialty_bonus' => 5,
            'is_published' => true,
        ];
    }
}

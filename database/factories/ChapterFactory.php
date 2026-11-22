<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    public function definition(): array
    {
        $town = fake()->unique()->city();

        return [
            'state_id' => State::factory(),
            'name' => $town.' Chapter',
            'lga' => $town,
        ];
    }
}

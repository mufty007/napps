<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Chapter;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Bright Future', 'Royal Crest', 'Excellence', 'Greenfield', 'Kings & Queens', 'Mercy Land', 'Unity', 'Heritage', 'Pinnacle', 'Morning Star'])
            .' '.fake()->randomElement(['Academy', 'International School', 'Schools', 'College', 'Nursery & Primary School']);

        return [
            'chapter_id' => Chapter::factory(),
            'name' => $name,
            'registration_number' => 'RC'.fake()->unique()->numerify('######'),
            'ministry_approval_number' => fake()->bothify('MOE/??/####'),
            'levels' => fake()->randomElements(School::LEVELS, fake()->numberBetween(1, 3)),
            'address' => fake()->streetAddress(),
            'pupil_count' => fake()->numberBetween(40, 1200),
            'phone' => '+234'.fake()->numerify('80########'),
            'membership_status' => MembershipStatus::Active,
        ];
    }

    public function status(MembershipStatus $status): static
    {
        return $this->state(fn () => ['membership_status' => $status]);
    }
}

<?php

namespace Database\Factories;

use App\Models\AuthorizedEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorizedEmail>
 */
class AuthorizedEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}

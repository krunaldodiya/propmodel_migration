<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'phone_verified' => 0,
            'status' => 1,
            'address' => fake()->address(),
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'timezone' => fake()->timezone(),
            'dob' => fake()->date(),
            'ref_code' => strtoupper(Str::random(8)),
        ];
    }

    /**
     * Indicate that the user is verified.
     */
    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'phone_verified' => 1,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user has 2FA enabled.
     */
    public function with2FA(): static
    {
        return $this->state(fn(array $attributes) => [
            '2fa_sms_enabled' => 1,
        ]);
    }
}

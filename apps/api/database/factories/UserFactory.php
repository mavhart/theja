<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organization_id'  => null,
            'name'             => fake()->name(),
            'email'            => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'         => static::$password ??= Hash::make('password'),
            'is_active'        => true,
            'remember_token'   => Str::random(10),
        ];
    }

    /** Utente associato a una Organization */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(['organization_id' => $organization->id]);
    }

    /** Utente non verificato */
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    /** Utente disattivato */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

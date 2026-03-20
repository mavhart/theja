<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name'               => fake()->company() . ' Ottica',
            'vat_number'         => $this->generateItalianVat(),
            'billing_email'      => fake()->companyEmail(),
            'stripe_customer_id' => null,
            'is_active'          => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    private function generateItalianVat(): string
    {
        return 'IT' . fake()->numerify('###########');
    }
}

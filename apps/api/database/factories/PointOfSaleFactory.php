<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PointOfSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointOfSale>
 */
class PointOfSaleFactory extends Factory
{
    protected $model = PointOfSale::class;

    public function definition(): array
    {
        return [
            'organization_id'                 => Organization::factory(),
            'name'                            => 'Ottica ' . fake()->lastName() . ' — ' . fake()->city(),
            'address'                         => fake()->streetAddress(),
            'city'                            => fake()->city(),
            'fiscal_code'                     => null,
            'vat_number'                      => null,
            'has_local_manager'               => true,
            'has_virtual_cash_register'       => false,
            'cash_register_hardware_configured' => false,
            'ai_analysis_enabled'             => false,
            'max_concurrent_web_sessions'     => 1,
            'max_mobile_devices'              => 0,
            'is_active'                       => true,
        ];
    }

    /** POS con cassa virtuale attiva */
    public function withVirtualCashRegister(): static
    {
        return $this->state(['has_virtual_cash_register' => true]);
    }

    /** POS con AI Analysis attiva */
    public function withAiAnalysis(): static
    {
        return $this->state(['ai_analysis_enabled' => true]);
    }

    /** POS con sessioni web extra (add-on) */
    public function withExtraSessions(int $max = 2): static
    {
        return $this->state(['max_concurrent_web_sessions' => $max]);
    }
}

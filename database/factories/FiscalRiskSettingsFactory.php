<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FiscalRiskSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalRiskSettings>
 */
final class FiscalRiskSettingsFactory extends Factory
{
    protected $model = FiscalRiskSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'max_interval' => 15,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
        ];
    }
}

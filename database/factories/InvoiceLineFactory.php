<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
final class InvoiceLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $months = fake()->numberBetween(0, 1);
        $weeks = fake()->numberBetween(0, 4);
        $days = fake()->numberBetween(0, 6);
        $daily = 9_000;
        $weekly = 50_000;
        $monthly = 180_000;

        $total = $months * $monthly + $weeks * $weekly + $days * $daily;

        return [
            'invoice_id' => Invoice::factory(),
            'vehicle_id' => Vehicle::factory(),
            'vehicle_label_snapshot' => 'AA-001-AA Renault Megane',
            'days_used' => $months * 30 + $weeks * 7 + $days,
            'months_billed' => $months,
            'weeks_billed' => $weeks,
            'days_billed' => $days,
            'daily_rate_cents' => $daily,
            'weekly_rate_cents' => $weekly,
            'monthly_rate_cents' => $monthly,
            'total_ht_cents' => $total,
            // No discount by default: gross = net.
            'gross_total_cents' => $total,
            'discount_cents' => 0,
            'applied_discount_id' => null,
            'applied_discount_label_snapshot' => null,
            'applied_discount_basis_points_snapshot' => null,
        ];
    }
}

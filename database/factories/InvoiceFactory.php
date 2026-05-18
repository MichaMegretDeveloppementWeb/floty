<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2026);
        $month = fake()->numberBetween(1, 12);

        $totalHtCents = fake()->numberBetween(10_000, 1_000_000);

        return [
            'company_id' => Company::factory(),
            'year' => $year,
            'month' => $month,
            'invoice_number' => sprintf(
                '%04d-%02d-%04d',
                $year,
                $month,
                fake()->unique()->numberBetween(1, 9999),
            ),
            'total_ht_cents' => $totalHtCents,
            // Sémantique « pas de réduction par défaut » · gross = net
            // (= total_ht_cents). Les tests RentalDiscount surchargent
            // ces champs via state() quand ils ont besoin d'une réduction.
            'total_gross_cents' => $totalHtCents,
            'total_discount_cents' => 0,
            'pdf_path' => sprintf('invoices/%d/test/%s.pdf', $year, fake()->uuid()),
            'pdf_hash' => fake()->sha256(),
            'generated_at' => now(),
            'generated_by_user_id' => User::factory(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function forYearMonth(int $year, int $month): static
    {
        return $this->state(fn () => [
            'year' => $year,
            'month' => $month,
            'invoice_number' => sprintf(
                '%04d-%02d-%04d',
                $year,
                $month,
                fake()->unique()->numberBetween(1, 9999),
            ),
        ]);
    }
}

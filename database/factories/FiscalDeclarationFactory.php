<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalDeclaration>
 */
final class FiscalDeclarationFactory extends Factory
{
    protected $model = FiscalDeclaration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fiscal_year' => fake()->numberBetween(2024, 2026),
            'status' => FiscalDeclarationStatus::Draft,
            'is_obsolete' => false,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    public function forYear(int $year): static
    {
        return $this->state(fn (): array => ['fiscal_year' => $year]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => FiscalDeclarationStatus::Draft]);
    }

    public function deferred(): static
    {
        return $this->state(fn (): array => ['status' => FiscalDeclarationStatus::Deferred]);
    }

    public function generated(): static
    {
        return $this->state(fn (): array => [
            'status' => FiscalDeclarationStatus::Generated,
            'generated_at' => now(),
            'generated_pdf_path' => sprintf('declarations/test/%s.pdf', fake()->uuid()),
            'generated_pdf_hash' => fake()->sha256(),
        ]);
    }

    public function obsolete(): static
    {
        return $this->state(fn (): array => [
            'status' => FiscalDeclarationStatus::Generated,
            'generated_at' => now()->subDays(2),
            'generated_pdf_path' => sprintf('declarations/test/%s.pdf', fake()->uuid()),
            'generated_pdf_hash' => fake()->sha256(),
            'is_obsolete' => true,
            'obsolete_at' => now(),
            'obsolete_reasons' => [
                [
                    'type' => InvalidationReasonType::ContractUpdated->value,
                    'occurred_at' => now()->toIso8601String(),
                    'actor_user_id' => 1,
                    'entity' => [
                        'type' => 'contract',
                        'id' => 1,
                        'label' => 'EA-001-AA · ACM · 14/10/2024 → 15/12/2024',
                    ],
                    'fields_changed' => ['start_date', 'end_date'],
                ],
            ],
        ]);
    }
}

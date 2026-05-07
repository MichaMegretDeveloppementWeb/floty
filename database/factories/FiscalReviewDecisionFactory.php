<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Company;
use App\Models\FiscalReviewDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalReviewDecision>
 */
final class FiscalReviewDecisionFactory extends Factory
{
    protected $model = FiscalReviewDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fiscal_year' => fake()->numberBetween(2024, 2026),
            'risk_code' => RiskCode::Chain,
            'cluster_fingerprint' => fake()->sha256(),
            'decision' => ReviewDecisionType::Conserved,
            'justification' => null,
            'decided_by' => User::factory(),
            'decided_at' => now(),
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

    public function conserved(?string $justification = null): static
    {
        return $this->state(fn (): array => [
            'decision' => ReviewDecisionType::Conserved,
            'justification' => $justification,
        ]);
    }

    public function requalified(?string $justification = null): static
    {
        return $this->state(fn (): array => [
            'decision' => ReviewDecisionType::Requalified,
            'justification' => $justification,
        ]);
    }

    public function withFingerprint(string $fingerprint): static
    {
        return $this->state(fn (): array => ['cluster_fingerprint' => $fingerprint]);
    }

    public function fortLevel(): static
    {
        return $this->state(fn (): array => ['risk_code' => RiskCode::ChainFort]);
    }
}

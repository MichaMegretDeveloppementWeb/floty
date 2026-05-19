<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Billing\BillingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test d'équivalence stricte critique (Lot 2 réductions commerciales) ·
 *
 * Garantit que le pipeline billing avec l'étage `DiscountApplier`
 * produit, en l'absence totale de réduction en base, exactement le
 * même résultat numérique que le pipeline pre-Lot 2 ·
 *   - `totalCents == grossTotalCents` (le net = le brut)
 *   - `totalDiscountCents == 0` (pas de réduction)
 *   - `appliedDiscountId == null` sur chaque ligne
 *   - les sommes mensuelles et annuelles préservées
 *
 * Cf. mémoire `feedback_conditional_perf_branch_equivalence` ·
 * « if fast → A else → B doit produire un résultat strictement
 * équivalent dans les 2 branches ». Ici A = pre-Lot 2 (pas de réduction
 * active), B = post-Lot 2 avec applier no-op.
 */
final class BillingPipelineEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sans_reduction_pipeline_post_lot2_renvoie_le_meme_resultat_numerique(): void
    {
        // Scenario : 1 entreprise, 2 véhicules, plusieurs contrats sur
        // une année, avec des tarifs variés.
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        foreach ([$vehicleA, $vehicleB] as $vehicle) {
            VehicleYearlyPricing::factory()->create([
                'vehicle_id' => $vehicle->id,
                'year' => 2024,
                'daily_rate_cents' => 9_000,
                'weekly_rate_cents' => 50_000,
                'monthly_rate_cents' => 180_000,
            ]);
        }

        Contract::factory()->create([
            'vehicle_id' => $vehicleA->id,
            'company_id' => $company->id,
            'start_date' => '2024-02-01',
            'end_date' => '2024-02-28',
        ]);
        Contract::factory()->create([
            'vehicle_id' => $vehicleB->id,
            'company_id' => $company->id,
            'start_date' => '2024-06-10',
            'end_date' => '2024-06-25',
        ]);

        $service = $this->app->make(BillingBreakdownService::class);

        // Aucune RentalDiscount en base : l'index sera vide ·
        // `DiscountApplier::applyToMonthlyResults` doit retourner les
        // inputs inchangés (branche early return `isEmpty()`).
        $breakdown = $service->byCompanyForYear($company->id, 2024);

        foreach ($breakdown->entries as $entry) {
            if ($entry->hasMissingPricing) {
                continue;
            }

            self::assertSame(
                $entry->totalCents,
                $entry->grossTotalCents,
                "Mois {$entry->month} · totalCents (net) doit être égal à grossTotalCents (brut) sans réduction.",
            );

            self::assertSame(
                0,
                $entry->totalDiscountCents,
                "Mois {$entry->month} · totalDiscountCents doit être 0 sans réduction.",
            );
        }

        self::assertSame(
            $breakdown->yearTotalCentsPartial,
            $breakdown->yearTotalGrossCentsPartial,
            'Total annuel partiel net == brut sans réduction.',
        );

        self::assertSame(
            0,
            $breakdown->yearTotalDiscountCentsPartial,
            'Total annuel réduction = 0 sans réduction.',
        );
    }

    #[Test]
    public function billing_calculator_expose_used_dates_par_ligne(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()->create([
            'vehicle_id' => $vehicle->id,
            'year' => 2024,
            'daily_rate_cents' => 9_000,
            'weekly_rate_cents' => 50_000,
            'monthly_rate_cents' => 180_000,
        ]);
        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-15',
        ]);

        $calc = $this->app->make(BillingCalculator::class)->calculate($company->id, 2024, 3);

        self::assertCount(1, $calc->lines);
        $line = $calc->lines[0];
        self::assertSame(6, $line->daysUsed, '6 jours du 10 au 15 mars inclus.');
        self::assertCount(6, $line->usedDates, 'usedDates exposé pour DiscountApplier.');
        self::assertSame(['2024-03-10', '2024-03-11', '2024-03-12', '2024-03-13', '2024-03-14', '2024-03-15'], $line->usedDates);
        self::assertSame($line->totalCents, $line->grossTotalCents, 'Sans réduction, net == brut.');
        self::assertSame(0, $line->discountCents);
        self::assertNull($line->appliedDiscountId);
    }
}

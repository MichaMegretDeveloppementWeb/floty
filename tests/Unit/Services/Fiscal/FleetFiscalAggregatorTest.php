<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal;

use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests des méthodes ajoutées par 04.A.bis pour la page Show :
 * `vehicleFullYearTax` et `vehicleAnnualTaxBreakdownByCompany`.
 */
final class FleetFiscalAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private FleetFiscalAggregator $aggregator;

    private const int YEAR = 2024;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class);
    }

    #[Test]
    public function vehicle_full_year_tax_retourne_le_montant_sans_prorata(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, self::YEAR);

        // WLTP 100 g/km essence M1 cat 1 = 173 € CO₂ + 100 € polluants = 273 €
        // (sans prorata, sans LCD - le contexte est daysAssigned = daysInYear).
        self::assertSame(273.0, $fullYearTax);
    }

    #[Test]
    public function vehicle_annual_tax_breakdown_retourne_une_ligne_par_entreprise(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        // Contrats non-LCD pour produire des taxes effectives.
        $contractsByPair = new ContractsByPair([
            $vehicle->id.'|10' => [
                $this->syntheticContract($vehicle->id, 10, '2024-01-15', 100),
            ],
            $vehicle->id.'|20' => [
                $this->syntheticContract($vehicle->id, 20, '2024-04-15', 200),
            ],
        ]);

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $contractsByPair,
            [],
            self::YEAR,
        );

        self::assertCount(2, $breakdown);

        $byCompany = collect($breakdown)->keyBy('companyId');

        self::assertSame(100, $byCompany[10]['days']);
        self::assertGreaterThan(0.0, $byCompany[10]['taxCo2']);
        self::assertGreaterThan(0.0, $byCompany[10]['taxPollutants']);
        self::assertEqualsWithDelta(
            $byCompany[10]['taxCo2'] + $byCompany[10]['taxPollutants'],
            $byCompany[10]['taxTotal'],
            0.01,
        );

        self::assertSame(200, $byCompany[20]['days']);
        self::assertGreaterThan(
            $byCompany[10]['taxTotal'],
            $byCompany[20]['taxTotal'],
            'Plus de jours = plus de taxe totale (prorata).',
        );
    }

    #[Test]
    public function vehicle_annual_tax_breakdown_renvoie_vide_si_aucune_attribution(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            new ContractsByPair([]),
            [],
            self::YEAR,
        );

        self::assertSame([], $breakdown);
    }

    /**
     * Contrat synthétique non-persisté de `$days` jours pour un couple
     * donné. Démarre à `$start` ; la durée garantit non-LCD si `$days > 30`
     * et la plage n'est pas un mois civil entier.
     */
    private function syntheticContract(int $vehicleId, int $companyId, string $start, int $days): Contract
    {
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');

        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicleId,
            'company_id' => $companyId,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    #[Test]
    public function vehicle_full_year_tax_breakdown_renvoie_le_detail_du_calcul(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        // WLTP 100 essence M1 cat 1 = 173 € CO₂ + 100 € polluants = 273 €
        self::assertSame(273.0, $breakdown->total);
        self::assertNotEmpty($breakdown->appliedRuleCodes);

        // Mono-VFC : un seul segment couvrant l'année entière. Les
        // tarifs et méthodes/catégories vivent désormais dans le segment
        // (chantier dette VFC L3 · cohérence affichage par segment).
        self::assertCount(1, $breakdown->taxSegments);
        $segment = $breakdown->taxSegments[0];
        self::assertSame(173.0, $segment->co2FullYearTariff);
        self::assertSame(100.0, $segment->pollutantsFullYearTariff);
        self::assertSame(173.0, $segment->co2Due);
        self::assertSame(100.0, $segment->pollutantsDue);
        self::assertSame('WLTP', $segment->co2Method->value);
        self::assertSame('category_1', $segment->pollutantCategory->value);
        self::assertSame(366, $segment->daysInSegment);
    }

    /**
     * Lot 3 D05 · garantit que la mémoïsation `$fullYearBreakdownCache`
     * retourne strictement la même instance DTO sur appels répétés pour un
     * même couple `(vehicle, year)` · prouve que le cache hit, sans
     * re-exécution du pipeline. Test de comportement, pas d'équivalence
     * sémantique (cette dernière étant déjà couverte par les autres tests).
     */
    #[Test]
    public function vehicle_full_year_tax_breakdown_est_memoise_sur_appels_repetes(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $first = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $second = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        // Identité d'instance · le cache renvoie le DTO précédemment construit
        self::assertSame($first, $second);
    }

    /**
     * Lot 3 D05 · garantit que le cache discrimine bien sur le couple
     * `(vehicleId, year)` · 2 véhicules distincts ou 2 années distinctes
     * doivent produire 2 instances différentes (pas de cross-pollution).
     */
    #[Test]
    public function vehicle_full_year_tax_breakdown_distingue_par_couple_vehicule_annee(): void
    {
        $vehicleA = $this->makeVehicleWltp100Essence();
        $vehicleB = $this->makeVehicleWltp100Essence();

        $a2024 = $this->aggregator->vehicleFullYearTaxBreakdown($vehicleA, self::YEAR);
        $b2024 = $this->aggregator->vehicleFullYearTaxBreakdown($vehicleB, self::YEAR);

        // Instances distinctes · 2 véhicules différents = 2 entrées cache
        self::assertNotSame($a2024, $b2024);
        // Mais valeurs équivalentes (mêmes caractéristiques fiscales)
        self::assertSame($a2024->total, $b2024->total);
    }

    /**
     * Chantier perf 2026-05-16 Option 3b · doctrine
     * `optimisations-conditionnelles.md` stratégie 2 · le prewarm
     * batch DOIT produire des taxes pleines année strictement
     * identiques aux appels individuels. Sinon le prewarm masque
     * silencieusement des écarts (ex. un VFC oublié dans le batch
     * → taxe trop basse). Test sur 3 véhicules de profils variés.
     */
    #[Test]
    public function prewarm_equivalent_aux_appels_individuels(): void
    {
        // Référence · 3 instances vierges, on calcule chaque taxe
        // individuellement (chemin sans prewarm).
        $aggregatorRef = $this->app->make(FleetFiscalAggregator::class);
        $v1Ref = $this->makeVehicleWltp100Essence();
        $v2Ref = $this->makeVehicleWltp100Essence();
        $v3Ref = $this->makeVehicleWltp100Essence();

        $t1Ref = $aggregatorRef->vehicleFullYearTax($v1Ref, self::YEAR);
        $t2Ref = $aggregatorRef->vehicleFullYearTax($v2Ref, self::YEAR);
        $t3Ref = $aggregatorRef->vehicleFullYearTax($v3Ref, self::YEAR);

        // Cible · même fixtures mais avec prewarm avant les appels
        // individuels (le prewarm doit remplir le cache de telle sorte
        // que les 3 appels ne déclenchent plus de pipeline).
        $aggregatorPrewarm = $this->app->make(FleetFiscalAggregator::class);
        $v1 = Vehicle::query()->find($v1Ref->id)->fresh();
        $v2 = Vehicle::query()->find($v2Ref->id)->fresh();
        $v3 = Vehicle::query()->find($v3Ref->id)->fresh();

        $aggregatorPrewarm->prewarmFullYearForVehicles([$v1, $v2, $v3], self::YEAR);

        $t1 = $aggregatorPrewarm->vehicleFullYearTax($v1, self::YEAR);
        $t2 = $aggregatorPrewarm->vehicleFullYearTax($v2, self::YEAR);
        $t3 = $aggregatorPrewarm->vehicleFullYearTax($v3, self::YEAR);

        self::assertSame($t1Ref, $t1, 'V1 · prewarm === individuel');
        self::assertSame($t2Ref, $t2, 'V2 · prewarm === individuel');
        self::assertSame($t3Ref, $t3, 'V3 · prewarm === individuel');
    }

    #[Test]
    public function prewarm_collapse_les_queries_vfc(): void
    {
        $v1 = $this->makeVehicleWltp100Essence();
        $v2 = $this->makeVehicleWltp100Essence();
        $v3 = $this->makeVehicleWltp100Essence();

        $aggregator = $this->app->make(FleetFiscalAggregator::class);

        // Baseline · sans prewarm, 1 query VFC par véhicule au moment
        // du vehicleFullYearTax (3 queries au total dans le pipeline).
        // Avec prewarm, ces 3 queries doivent collapser en 1 seule.
        DB::enableQueryLog();
        DB::flushQueryLog();

        $aggregator->prewarmFullYearForVehicles([$v1, $v2, $v3], self::YEAR);

        $prewarmQueries = DB::getQueryLog();
        DB::flushQueryLog();

        // Après prewarm, les appels suivants ne doivent JAMAIS retoucher
        // la table vehicle_fiscal_characteristics (cache hit).
        $aggregator->vehicleFullYearTax($v1, self::YEAR);
        $aggregator->vehicleFullYearTax($v2, self::YEAR);
        $aggregator->vehicleFullYearTax($v3, self::YEAR);

        $afterPrewarmQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $vfcQueriesInPrewarm = array_filter(
            $prewarmQueries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );
        $vfcQueriesAfter = array_filter(
            $afterPrewarmQueries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );

        self::assertCount(1, $vfcQueriesInPrewarm, 'prewarm = 1 seule query VFC batch');
        self::assertCount(0, $vfcQueriesAfter, 'après prewarm, plus aucune query VFC pour les véhicules cachés');
    }

    #[Test]
    public function prewarm_idempotent_sur_vehicules_deja_caches(): void
    {
        $v1 = $this->makeVehicleWltp100Essence();
        $aggregator = $this->app->make(FleetFiscalAggregator::class);

        // 1er appel · pipeline complet
        $first = $aggregator->vehicleFullYearTax($v1, self::YEAR);

        // 2e prewarm sur le même véhicule · doit être no-op (idempotent).
        DB::enableQueryLog();
        DB::flushQueryLog();

        $aggregator->prewarmFullYearForVehicles([$v1], self::YEAR);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $vfcQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );

        self::assertCount(0, $vfcQueries, 'prewarm idempotent · véhicule déjà caché, aucune query');
        // Et la valeur reste cohérente.
        self::assertSame($first, $aggregator->vehicleFullYearTax($v1, self::YEAR));
    }

    // --- prewarmVfcSegmentsForVehicles (chantier perf Planning 2026-05-16) ---

    #[Test]
    public function prewarm_vfc_segments_equivalent_pour_vehicle_full_year_tax_breakdown(): void
    {
        // Doctrine `optimisations-conditionnelles.md` stratégie 2 · les
        // valeurs `vehicleFullYearTaxBreakdown` avec prewarm DOIVENT être
        // strictement identiques à celles sans prewarm. Sinon le prewarm
        // masque silencieusement un écart fiscal · risque inacceptable.
        $aggregatorRef = $this->app->make(FleetFiscalAggregator::class);
        $v1Ref = $this->makeVehicleWltp100Essence();
        $v2Ref = $this->makeVehicleWltp100Essence();

        $b1Ref = $aggregatorRef->vehicleFullYearTaxBreakdown($v1Ref, self::YEAR);
        $b2Ref = $aggregatorRef->vehicleFullYearTaxBreakdown($v2Ref, self::YEAR);

        $aggregatorPre = $this->app->make(FleetFiscalAggregator::class);
        $v1 = Vehicle::query()->find($v1Ref->id)->fresh();
        $v2 = Vehicle::query()->find($v2Ref->id)->fresh();

        $aggregatorPre->prewarmVfcSegmentsForVehicles([$v1, $v2], self::YEAR);

        $b1 = $aggregatorPre->vehicleFullYearTaxBreakdown($v1, self::YEAR);
        $b2 = $aggregatorPre->vehicleFullYearTaxBreakdown($v2, self::YEAR);

        self::assertSame($b1Ref->total, $b1->total, 'V1 · total prewarm === individuel');
        self::assertSame($b2Ref->total, $b2->total, 'V2 · total prewarm === individuel');
        self::assertSame($b1Ref->daysInYear, $b1->daysInYear);
        self::assertCount(count($b1Ref->taxSegments), $b1->taxSegments);
        self::assertSame($b1Ref->appliedRuleCodes, $b1->appliedRuleCodes);
    }

    #[Test]
    public function prewarm_vfc_segments_equivalent_pour_vehicle_annual_tax(): void
    {
        // Idem pour `vehicleAnnualTax` qui passe par
        // `executeWithPreloadedVfcSegments` quand le cache est rempli.
        $aggregatorRef = $this->app->make(FleetFiscalAggregator::class);
        $v1Ref = $this->makeVehicleWltp100Essence();
        $v2Ref = $this->makeVehicleWltp100Essence();
        $company = Company::factory()->create();

        $contracts = new ContractsByPair([
            $v1Ref->id.'|'.$company->id => [
                $this->syntheticContract($v1Ref->id, $company->id, '2024-02-01', 100),
            ],
            $v2Ref->id.'|'.$company->id => [
                $this->syntheticContract($v2Ref->id, $company->id, '2024-04-01', 100),
            ],
        ]);

        $t1Ref = $aggregatorRef->vehicleAnnualTax($v1Ref, $contracts, [], self::YEAR);
        $t2Ref = $aggregatorRef->vehicleAnnualTax($v2Ref, $contracts, [], self::YEAR);

        $aggregatorPre = $this->app->make(FleetFiscalAggregator::class);
        $v1 = Vehicle::query()->find($v1Ref->id)->fresh();
        $v2 = Vehicle::query()->find($v2Ref->id)->fresh();

        $aggregatorPre->prewarmVfcSegmentsForVehicles([$v1, $v2], self::YEAR);

        $t1 = $aggregatorPre->vehicleAnnualTax($v1, $contracts, [], self::YEAR);
        $t2 = $aggregatorPre->vehicleAnnualTax($v2, $contracts, [], self::YEAR);

        self::assertSame($t1Ref, $t1, 'V1 · prewarm === individuel');
        self::assertSame($t2Ref, $t2, 'V2 · prewarm === individuel');
    }

    #[Test]
    public function prewarm_vfc_segments_collapse_les_queries_vfc(): void
    {
        // Avec prewarm, les 3 appels `vehicleFullYearTaxBreakdown` ne
        // doivent JAMAIS retoucher la table vehicle_fiscal_characteristics.
        $v1 = $this->makeVehicleWltp100Essence();
        $v2 = $this->makeVehicleWltp100Essence();
        $v3 = $this->makeVehicleWltp100Essence();

        $aggregator = $this->app->make(FleetFiscalAggregator::class);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $aggregator->prewarmVfcSegmentsForVehicles([$v1, $v2, $v3], self::YEAR);
        $prewarmQueries = DB::getQueryLog();
        DB::flushQueryLog();

        $aggregator->vehicleFullYearTaxBreakdown($v1, self::YEAR);
        $aggregator->vehicleFullYearTaxBreakdown($v2, self::YEAR);
        $aggregator->vehicleFullYearTaxBreakdown($v3, self::YEAR);

        $afterQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $vfcInPrewarm = array_filter(
            $prewarmQueries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );
        $vfcAfter = array_filter(
            $afterQueries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );

        self::assertCount(1, $vfcInPrewarm, 'prewarm = 1 seule query VFC batch');
        self::assertCount(0, $vfcAfter, 'après prewarm, aucune query VFC pour les véhicules cachés');
    }

    #[Test]
    public function prewarm_vfc_segments_idempotent(): void
    {
        $v1 = $this->makeVehicleWltp100Essence();
        $aggregator = $this->app->make(FleetFiscalAggregator::class);

        $aggregator->prewarmVfcSegmentsForVehicles([$v1], self::YEAR);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $aggregator->prewarmVfcSegmentsForVehicles([$v1], self::YEAR);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $vfcQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'vehicle_fiscal_characteristics'),
        );

        self::assertCount(0, $vfcQueries, '2e prewarm sur véhicule déjà caché = no-op');
    }

    private function makeVehicleWltp100Essence(): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6',
            'pollutant_category' => 'category_1',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 100,
            'co2_nedc' => null,
            'taxable_horsepower' => null,
        ]);

        return $vehicle->fresh(['fiscalCharacteristics']);
    }

    /**
     * Bloc Φ.bis · vérifie qu'un contrat à cheval sur la scission
     * polluants du 01/03/2026 (LF 2026 art. 58 V IV, +30 %) expose
     * 2 segments distincts dans son breakdown · le premier avec tarif
     * Cat1 100 €/an (R-2026-014), le second avec tarif Cat1 130 €/an
     * (R-2026-014-bis). Garantit la transparence pédagogique de l'UI
     * fiche contrat.
     */
    #[Test]
    public function contract_tax_breakdown_expose_2_segments_pour_scission_polluants_2026(): void
    {
        $vehicle = $this->makeVehicleWltp100EssenceFor(2026);
        $company = Company::factory()->create();
        // Contrat 15/01/2026 → 24/04/2026 · 100 jours, à cheval 01/03.
        $contract = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2026-01-15',
            'end_date' => '2026-04-24',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
        ]);

        $breakdown = $this->aggregator->contractTaxBreakdown($contract, []);

        self::assertCount(1, $breakdown->years);
        $year = $breakdown->years[0];
        self::assertSame(2026, $year->year);
        self::assertSame(100, $year->daysAssigned);

        // 2 segments dans l'année · scission au 01/03/2026.
        self::assertCount(2, $year->segments);

        // Segment 1 · 01/01-28/02 · R-2026-014 · Cat1 = 100 €/an.
        $seg1 = $year->segments[0];
        self::assertSame('2026-01-01', $seg1->effectiveFromInYear);
        self::assertSame('2026-02-28', $seg1->effectiveToInYear);
        self::assertSame(45, $seg1->daysAssignedToContract); // 15/01 → 28/02
        self::assertSame(100.0, $seg1->pollutantsFullYearTariff);
        self::assertSame(12.33, $seg1->pollutantsDue); // 100 × 45/365
        self::assertContains('R-2026-014', $seg1->appliedRuleCodes);

        // Segment 2 · 01/03-... · R-2026-014-bis · Cat1 = 130 €/an.
        $seg2 = $year->segments[1];
        self::assertSame('2026-03-01', $seg2->effectiveFromInYear);
        self::assertSame(55, $seg2->daysAssignedToContract); // 01/03 → 24/04
        self::assertSame(130.0, $seg2->pollutantsFullYearTariff);
        self::assertSame(19.59, $seg2->pollutantsDue); // 130 × 55/365
        self::assertContains('R-2026-014-bis', $seg2->appliedRuleCodes);

        // Total année · somme cohérente des 2 segments.
        self::assertSame(31.92, $year->pollutantsDue); // 12,33 + 19,59
        // CO₂ pas scindé en 2026 · 213 €/an constant.
        self::assertSame(213.0, $year->co2FullYearTariff);
        self::assertSame(58.36, $year->co2Due); // 213 × 100/365
    }

    private function makeVehicleWltp100EssenceFor(int $year): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => sprintf('%d-01-01', $year - 2),
            'effective_to' => null,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6',
            'pollutant_category' => 'category_1',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 100,
            'co2_nedc' => null,
            'taxable_horsepower' => null,
        ]);

        return $vehicle->fresh(['fiscalCharacteristics']);
    }
}

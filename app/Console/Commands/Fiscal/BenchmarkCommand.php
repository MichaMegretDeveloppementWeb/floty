<?php

declare(strict_types=1);

namespace App\Console\Commands\Fiscal;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Benchmark the fiscal engine over 4 representative scenarios on the
 * current dataset, then archive the timings as JSON for historical
 * comparison.
 *
 * Methodology: for each scenario, run N times and keep the median wall
 * time (resistant to outliers caused by system load). Wall time is
 * measured around the targeted call only, with millisecond precision.
 *
 *   Usage : `php artisan fiscal:benchmark --year=2024 --runs=5`
 *   Output: console table + JSON archive in
 *           `storage/app/benchmarks/{label}-{year}-{timestamp}.json`
 */
final class BenchmarkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fiscal:benchmark
                            {--year=2024 : Année fiscale cible}
                            {--runs=5 : Nombre de runs par scénario}
                            {--label=baseline : Étiquette du fichier JSON archivé}';

    /**
     * @var string
     */
    protected $description = 'Mesure la performance du moteur fiscal sur 4 scénarios représentatifs.';

    public function __construct(
        private readonly FleetFiscalAggregator $aggregator,
        private readonly ContractQueryService $contracts,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
    ) {
        parent::__construct();
    }

    /**
     * Run the 4 scenarios and render both the console table and the JSON
     * archive.
     */
    public function handle(): int
    {
        $year = (int) $this->option('year');
        $runs = (int) $this->option('runs');
        $label = (string) $this->option('label');

        $this->info("Benchmark fiscal · année {$year} · {$runs} runs/scénario");

        $results = [];

        $results[] = $this->measureScenario('S1 · companyAnnualTaxBreakdownByVehicle (toutes entreprises)', $runs, function () use ($year): void {
            $contractsByPair = $this->contracts->loadContractsByPair($year);
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));
            $vehicleEvents = $this->contracts->loadVehicleEventsByVehicle(array_keys($vehicleIds));

            foreach ($this->companies->findAllOrderedByName() as $company) {
                $this->aggregator->companyAnnualTaxBreakdownByVehicle(
                    $company->id,
                    $vehiclesById,
                    $contractsByPair,
                    $vehicleEvents,
                    $year,
                );
            }
        });

        $results[] = $this->measureScenario('S2 · companyAnnualTax (1 entreprise)', $runs, function () use ($year): void {
            $contractsByPair = $this->contracts->loadContractsByPair($year);
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));
            $vehicleEvents = $this->contracts->loadVehicleEventsByVehicle(array_keys($vehicleIds));

            $firstCompany = $this->companies->findAllOrderedByName()->first();
            if ($firstCompany === null) {
                return;
            }

            $this->aggregator->companyAnnualTax(
                $firstCompany->id,
                $vehiclesById,
                $contractsByPair,
                $vehicleEvents,
                $year,
            );
        });

        $results[] = $this->measureScenario('S3 · vehicleFullYearTaxBreakdown (tous véhicules)', $runs, function () use ($year): void {
            $contractsByPair = $this->contracts->loadContractsByPair($year);
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));

            foreach ($vehiclesById as $vehicle) {
                $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
            }
        });

        $results[] = $this->measureScenario('S4 · vehicleFullYearTax (1 véhicule multi-VFC)', $runs, function () use ($year): void {
            $multiVfcVehicle = $this->findMultiVfcVehicle($year);
            if ($multiVfcVehicle === null) {
                return;
            }
            $this->aggregator->vehicleFullYearTax($multiVfcVehicle, $year);
        });

        $this->renderTable($results);
        $this->writeJsonArchive($results, $label, $year, $runs);

        return self::SUCCESS;
    }

    /**
     * Run `$callback` `$runs` times and return min/median/max wall times in milliseconds.
     *
     * @return array{name: string, runs: list<float>, median_ms: float, min_ms: float, max_ms: float}
     */
    private function measureScenario(string $name, int $runs, callable $callback): array
    {
        $times = [];
        for ($i = 0; $i < $runs; $i++) {
            $start = microtime(true);
            $callback();
            $times[] = (microtime(true) - $start) * 1000.0;
        }

        sort($times);
        $median = $times[(int) floor(count($times) / 2)];

        return [
            'name' => $name,
            'runs' => $times,
            'median_ms' => round($median, 2),
            'min_ms' => round(min($times), 2),
            'max_ms' => round(max($times), 2),
        ];
    }

    /**
     * Render the result set as a console table.
     *
     * @param  list<array{name: string, runs: list<float>, median_ms: float, min_ms: float, max_ms: float}>  $results
     */
    private function renderTable(array $results): void
    {
        $this->newLine();
        $this->table(
            ['Scénario', 'Médiane (ms)', 'Min (ms)', 'Max (ms)', 'Runs'],
            array_map(static fn (array $r): array => [
                $r['name'],
                number_format($r['median_ms'], 2, '.', ' '),
                number_format($r['min_ms'], 2, '.', ' '),
                number_format($r['max_ms'], 2, '.', ' '),
                count($r['runs']),
            ], $results),
        );
    }

    /**
     * Persist the result set as a timestamped JSON archive under
     * `storage/app/private/benchmarks/`.
     *
     * @param  list<array{name: string, runs: list<float>, median_ms: float, min_ms: float, max_ms: float}>  $results
     */
    private function writeJsonArchive(array $results, string $label, int $year, int $runs): void
    {
        $timestamp = Carbon::now()->format('Ymd-His');
        $path = sprintf('benchmarks/%s-%d-%s.json', $label, $year, $timestamp);
        $payload = [
            'label' => $label,
            'year' => $year,
            'runs_per_scenario' => $runs,
            'measured_at' => Carbon::now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'scenarios' => $results,
        ];

        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $this->info("Archive JSON : storage/app/private/{$path}");
    }

    /**
     * Pick the first vehicle with at least two fiscal characteristics on
     * the year; fall back to the first available vehicle when none qualify.
     */
    private function findMultiVfcVehicle(int $year): ?Vehicle
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicles = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));

        foreach ($vehicles as $vehicle) {
            $vfcCount = $vehicle->fiscalCharacteristics()->count();
            if ($vfcCount >= 2) {
                return $vehicle;
            }
        }

        return $vehicles->first();
    }
}

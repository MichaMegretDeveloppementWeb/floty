<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Fiscal;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration de la commande `fiscal:benchmark`.
 */
final class BenchmarkCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);
        Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'contract_type' => ContractType::Lld,
        ]);
    }

    #[Test]
    public function la_commande_s_execute_sans_erreur(): void
    {
        $this->artisan('fiscal:benchmark', ['--year' => 2024, '--runs' => 2])
            ->assertSuccessful();
    }

    #[Test]
    public function la_commande_archive_un_fichier_json(): void
    {
        $this->artisan('fiscal:benchmark', ['--year' => 2024, '--runs' => 1, '--label' => 'unit-test'])
            ->assertSuccessful();

        $files = Storage::disk('local')->files('benchmarks');
        self::assertNotEmpty($files);
        self::assertTrue(
            collect($files)->contains(static fn (string $path): bool => str_contains($path, 'unit-test-2024-')),
            'Le fichier JSON archivé doit refléter le label fourni.',
        );
    }

    #[Test]
    public function le_json_archive_contient_les_4_scenarios(): void
    {
        $this->artisan('fiscal:benchmark', ['--year' => 2024, '--runs' => 1, '--label' => 'shape-test'])
            ->assertSuccessful();

        $file = collect(Storage::disk('local')->files('benchmarks'))
            ->first(static fn (string $path): bool => str_contains($path, 'shape-test-'));
        self::assertNotNull($file);

        $payload = json_decode(Storage::disk('local')->get($file), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('shape-test', $payload['label']);
        self::assertSame(2024, $payload['year']);
        self::assertSame(1, $payload['runs_per_scenario']);
        self::assertCount(4, $payload['scenarios']);
        foreach ($payload['scenarios'] as $scenario) {
            self::assertArrayHasKey('name', $scenario);
            self::assertArrayHasKey('median_ms', $scenario);
            self::assertGreaterThanOrEqual(0.0, $scenario['median_ms']);
        }
    }

    #[Test]
    public function l_option_label_par_defaut_est_baseline(): void
    {
        $this->artisan('fiscal:benchmark', ['--year' => 2024, '--runs' => 1])
            ->assertSuccessful();

        $files = Storage::disk('local')->files('benchmarks');
        self::assertTrue(
            collect($files)->contains(static fn (string $path): bool => str_contains($path, 'baseline-2024-')),
        );
    }
}

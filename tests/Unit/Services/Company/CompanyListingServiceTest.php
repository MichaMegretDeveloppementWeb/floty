<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Company;

use App\Models\Company;
use App\Services\Company\CompanyListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre `listForOptions()` + `colorOptions()` après l'éclatement
 * SRP de `CompanyQueryService` en 3 sous-services thématiques
 * (Lot 4 D08 / F-11-004). Reprend les assertions de l'ancien
 * `CompanyQueryServiceTest` pour garantir l'équivalence sémantique.
 *
 * `listPaginated()` est testé par les Feature
 * `tests/Feature/User/Company/CompanyControllerTest::index` (intégration
 * complète repo + agrégats fiscaux + DTO Inertia).
 */
final class CompanyListingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompanyListingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CompanyListingService::class);
    }

    #[Test]
    public function list_for_options_filtre_les_inactives(): void
    {
        Company::factory()->create(['is_active' => true]);
        Company::factory()->create(['is_active' => false]);

        $result = $this->service->listForOptions();

        self::assertCount(1, $result->toArray());
    }

    #[Test]
    public function color_options_renvoie_un_dto_par_couleur(): void
    {
        $result = $this->service->colorOptions()->toArray();

        self::assertNotEmpty($result);
        self::assertArrayHasKey('value', $result[0]);
        self::assertArrayHasKey('label', $result[0]);
    }
}

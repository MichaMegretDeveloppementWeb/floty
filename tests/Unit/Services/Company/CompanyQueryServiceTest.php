<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Company;

use App\Models\Company;
use App\Services\Company\CompanyQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration repos + agrégateur fiscal du service
 * `CompanyQueryService` post-migration vers les Repositories.
 */
final class CompanyQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompanyQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CompanyQueryService::class);
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

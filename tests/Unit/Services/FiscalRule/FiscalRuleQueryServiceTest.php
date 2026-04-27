<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FiscalRule;

use App\Models\FiscalRule;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FiscalRuleQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalRuleQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(FiscalRuleQueryService::class);
    }

    #[Test]
    public function list_for_year_filtre_par_annee_et_trie_par_display_order(): void
    {
        $year = 2024;
        $second = FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'display_order' => 50,
        ]);
        $first = FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'display_order' => 10,
        ]);
        FiscalRule::factory()->create(['fiscal_year' => 2025]);

        $result = $this->service->listForYear($year)->toArray();

        self::assertCount(2, $result);
        self::assertSame($first->id, $result[0]['id']);
        self::assertSame($second->id, $result[1]['id']);
    }
}

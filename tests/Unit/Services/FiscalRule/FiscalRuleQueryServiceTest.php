<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FiscalRule;

use App\Models\FiscalRule;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    #[Test]
    public function regle_full_year_expose_dates_clippees_et_is_full_year_true(): void
    {
        $year = 2024;
        FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'applicability_start' => Carbon::parse('2024-01-01'),
            'applicability_end' => Carbon::parse('2024-12-31'),
            'display_order' => 1,
        ]);

        $result = $this->service->listForYear($year)->toArray();

        self::assertCount(1, $result);
        self::assertSame('2024-01-01', $result[0]['applicabilityStartInYear']);
        self::assertSame('2024-12-31', $result[0]['applicabilityEndInYear']);
        self::assertTrue($result[0]['isFullYear']);
    }

    #[Test]
    public function regle_partielle_a_dates_clippees_et_is_full_year_false(): void
    {
        // Règle partielle : valide du 01/07/2024 au 31/12/2024 (pas de
        // recouvrement avec l'année précédente). Apparition mid-year.
        $year = 2024;
        FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'applicability_start' => Carbon::parse('2024-07-01'),
            'applicability_end' => Carbon::parse('2024-12-31'),
            'display_order' => 1,
        ]);

        $result = $this->service->listForYear($year)->toArray();

        self::assertSame('2024-07-01', $result[0]['applicabilityStartInYear']);
        self::assertSame('2024-12-31', $result[0]['applicabilityEndInYear']);
        self::assertFalse($result[0]['isFullYear']);
    }

    #[Test]
    public function regle_qui_deborde_de_l_annee_consultee_est_clippee_aux_bornes_de_l_annee(): void
    {
        // Règle effective 01/07/2024 -> 03/03/2025. Vue depuis l'année
        // 2024, doit être clippée à 01/07/2024 -> 31/12/2024.
        $year = 2024;
        FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'applicability_start' => Carbon::parse('2024-07-01'),
            'applicability_end' => Carbon::parse('2025-03-03'),
            'display_order' => 1,
        ]);

        $result = $this->service->listForYear($year)->toArray();

        self::assertSame('2024-07-01', $result[0]['applicabilityStartInYear']);
        self::assertSame('2024-12-31', $result[0]['applicabilityEndInYear']);
        self::assertFalse($result[0]['isFullYear']);
    }

    #[Test]
    public function regle_open_ended_clippe_l_end_a_la_fin_de_l_annee(): void
    {
        // applicability_end = null (open-ended) ; le DTO doit substituer
        // year-12-31 et signaler isFullYear=true si start = year-01-01.
        $year = 2024;
        FiscalRule::factory()->create([
            'fiscal_year' => $year,
            'applicability_start' => Carbon::parse('2024-01-01'),
            'applicability_end' => null,
            'display_order' => 1,
        ]);

        $result = $this->service->listForYear($year)->toArray();

        self::assertSame('2024-12-31', $result[0]['applicabilityEndInYear']);
        self::assertTrue($result[0]['isFullYear']);
    }
}

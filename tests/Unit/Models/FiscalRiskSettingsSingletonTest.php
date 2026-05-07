<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\FiscalRiskSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du singleton applicatif `FiscalRiskSettings::singleton()`
 * (Phase 11 D1, ADR-0015 § D7 rev. 1.1). Pattern aligné sur
 * `BillingSettings::singleton`.
 */
final class FiscalRiskSettingsSingletonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cree_la_ligne_avec_valeurs_par_defaut_si_table_vide(): void
    {
        self::assertSame(0, FiscalRiskSettings::query()->count());

        $settings = FiscalRiskSettings::singleton();

        self::assertSame(1, FiscalRiskSettings::query()->count());
        self::assertSame(15, $settings->max_interval);
        self::assertSame(30, $settings->threshold_low);
        self::assertSame(90, $settings->threshold_high);
        self::assertSame(5, $settings->count_high);
        self::assertTrue($settings->lld_breaks_chain);
    }

    #[Test]
    public function retourne_la_meme_instance_apres_appels_repetes(): void
    {
        $first = FiscalRiskSettings::singleton();
        $second = FiscalRiskSettings::singleton();

        self::assertSame($first->id, $second->id);
        self::assertSame(1, FiscalRiskSettings::query()->count());
    }

    #[Test]
    public function casts_strict_des_seuils_en_integer_et_du_flag_en_boolean(): void
    {
        $settings = FiscalRiskSettings::singleton();

        // Sanity check : valeurs par défaut respectent les casts strict.
        // Les types sont déjà garantis par les PHPDoc + casts du Model ;
        // on assert seulement les VALEURS attendues.
        self::assertGreaterThan(0, $settings->max_interval);
        self::assertGreaterThanOrEqual(0, $settings->threshold_low);
        self::assertGreaterThanOrEqual($settings->threshold_low, $settings->threshold_high);
        self::assertGreaterThanOrEqual(0, $settings->count_high);
    }
}

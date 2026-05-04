<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Contract\ContractType;
use App\Models\Contract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de {@see Contract::deriveTypeFromDates()} - convention
 * BOFiP § 180-190 (LCD si durée ≤ 30 j ou mois civil entier).
 *
 * Test pur (pas de DB), donc `PHPUnit\Framework\TestCase` direct (pas
 * `Tests\TestCase`) - pas besoin du framework Laravel.
 */
final class ContractDeriveTypeTest extends TestCase
{
    #[Test]
    public function un_jour_seul_est_lcd(): void
    {
        $type = Contract::deriveTypeFromDates('2024-05-15', '2024-05-15');

        $this->assertSame(ContractType::Lcd, $type);
    }

    #[Test]
    public function trente_jours_exactement_est_lcd(): void
    {
        // 1er mai → 30 mai = 30 jours (inclusif)
        $type = Contract::deriveTypeFromDates('2024-05-01', '2024-05-30');

        $this->assertSame(ContractType::Lcd, $type);
    }

    #[Test]
    public function trente_et_un_jours_est_lld_sauf_mois_civil(): void
    {
        // 1er mai → 31 mai = 31 jours, mais c'est un mois civil entier → LCD
        $type = Contract::deriveTypeFromDates('2024-05-01', '2024-05-31');

        $this->assertSame(ContractType::Lcd, $type);
    }

    #[Test]
    public function trente_et_un_jours_a_cheval_sur_deux_mois_est_lld(): void
    {
        // 15 avril → 15 mai = 31 jours, pas un mois civil entier → LLD
        $type = Contract::deriveTypeFromDates('2024-04-15', '2024-05-15');

        $this->assertSame(ContractType::Lld, $type);
    }

    #[Test]
    public function fevrier_complet_28_jours_est_lcd_mois_civil(): void
    {
        // Février 2023 (non bissextile) entier = 28 jours, mois civil → LCD
        $type = Contract::deriveTypeFromDates('2023-02-01', '2023-02-28');

        $this->assertSame(ContractType::Lcd, $type);
    }

    #[Test]
    public function fragment_de_fevrier_15_jours_est_lcd_par_duree(): void
    {
        // 1er → 15 février = 15 jours (≤ 30) → LCD via la 1re branche
        $type = Contract::deriveTypeFromDates('2024-02-01', '2024-02-15');

        $this->assertSame(ContractType::Lcd, $type);
    }

    #[Test]
    public function soixante_jours_a_cheval_est_lld(): void
    {
        // BOFiP § 230 ex. 2 : 1er nov → 30 déc = 60 jours
        $type = Contract::deriveTypeFromDates('2024-11-01', '2024-12-30');

        $this->assertSame(ContractType::Lld, $type);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Traçabilité explicite des constantes magiques fiscales 2025 (PUSH-3 ·
 * audit fiscal renforcé 14/05/2026).
 *
 * Chaque constante numérique du moteur 2025 doit être tracée à un texte
 * légal précis (CIBS article + paragraphe). Ce test verrouille la valeur
 * attendue : une modification accidentelle (typo, mise à jour mal
 * propagée) sera détectée immédiatement.
 *
 * **Pourquoi un test dédié** : les goldens (R2025_PricingScalesTest,
 * R2025_ExhaustiveGoldensTest, R2025_023_E85AbatementTest) testent les
 * comportements aux bordures (250/251 E85, 30/31 LCD, etc.) mais ne
 * matérialisent pas explicitement le **lien constante ↔ source légale**.
 * Ce test sert de documentation exécutable.
 *
 * **Si une constante doit changer** : vérifier d'abord que la source
 * légale a effectivement été modifiée (LF nouvelle, BOFiP réécrit),
 * puis mettre à jour le test + la classe simultanément.
 */
final class FiscalConstantsTraceabilityTest extends TestCase
{
    #[Test]
    public function r2025_021_threshold_lcd_30_jours_vient_de_cibs_l421_129(): void
    {
        // CIBS L. 421-129 (en vigueur 01/01/2025) ·
        //   « véhicules qui [...] sont pris en location pour une période
        //     n'excédant pas un mois civil ou trente jours consécutifs »
        // BOFiP `BOI-AIS-MOB-10-30-20-20250528` § 180-190 (doctrine
        // opposable). Le seuil 30 est inclusif (≤ 30 → exempté).
        self::assertSame(30, R2025_021_ShortTermRental::THRESHOLD_DAYS);
    }

    #[Test]
    public function r2025_023_e85_reduction_factor_60_pourcent_vient_de_cibs_l421_125(): void
    {
        // CIBS L. 421-125 (réformé par LF 2024 art. 97-23°, en vigueur
        // 01/01/2025) : « abattement de 40 % des émissions de dioxyde
        // de carbone ». 40 % d'abattement = facteur de réduction 0,60
        // (CO₂ retenu = CO₂ × 0,60).
        $reflection = new \ReflectionClass(R2025_023_E85Abatement::class);
        $constant = $reflection->getReflectionConstant('WLTP_NEDC_REDUCTION_FACTOR');
        self::assertNotFalse($constant);
        self::assertSame(0.60, $constant->getValue());
    }

    #[Test]
    public function r2025_023_e85_plafond_co2_250_grammes_inclusif_vient_de_cibs_l421_125(): void
    {
        // CIBS L. 421-125 (en vigueur 01/01/2025) : « sauf lorsque ces
        // émissions excèdent 250 grammes par kilomètre ». « Excèdent »
        // = strictement supérieur : 250 exact reste éligible, 251 non.
        $reflection = new \ReflectionClass(R2025_023_E85Abatement::class);
        $constant = $reflection->getReflectionConstant('CO2_THRESHOLD_INCLUSIVE');
        self::assertNotFalse($constant);
        self::assertSame(250, $constant->getValue());
    }

    #[Test]
    public function r2025_023_e85_reduction_pa_2_cv_vient_de_cibs_l421_125(): void
    {
        // CIBS L. 421-125 (en vigueur 01/01/2025) : « abattement de
        // [...] 2 chevaux administratifs pour la puissance
        // administrative ». PA retenue = PA - 2 (clampée à 0).
        $reflection = new \ReflectionClass(R2025_023_E85Abatement::class);
        $constant = $reflection->getReflectionConstant('PA_REDUCTION_CV');
        self::assertNotFalse($constant);
        self::assertSame(2, $constant->getValue());
    }

    #[Test]
    public function r2025_023_e85_plafond_pa_12_cv_inclusif_vient_de_cibs_l421_125(): void
    {
        // CIBS L. 421-125 (en vigueur 01/01/2025) : « sauf lorsque
        // cette dernière excède 12 chevaux administratifs ». « Excède »
        // = strictement supérieur : 12 CV exact reste éligible,
        // 13 CV non.
        $reflection = new \ReflectionClass(R2025_023_E85Abatement::class);
        $constant = $reflection->getReflectionConstant('PA_THRESHOLD_INCLUSIVE');
        self::assertNotFalse($constant);
        self::assertSame(12, $constant->getValue());
    }

    #[Test]
    public function r2025_002_denominateur_prorata_365_jours_non_bissextile(): void
    {
        // R-2025-002 utilise `$context->daysInYear` qui vaut 365 en
        // 2025 (non bissextile, calculé dynamiquement par DateTime).
        // C'est le dénominateur du prorata journalier (CIBS L. 421-107).
        $daysInYear2025 = (int) (new \DateTime('2025-12-31'))->format('z') + 1;

        self::assertSame(365, $daysInYear2025);
    }
}

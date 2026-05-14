<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Year2024\Exemption\R2024_017_ConditionalHybridExemption;
use App\Fiscal\Year2024\Transversal\R2024_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2024\Year2024Boot;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Transversal\R2025_033_FleetGreeningIncentiveTax;
use App\Fiscal\Year2025\Year2025Boot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifications anti-inversion (chaque règle est dans le bon catalogue).
 *
 * Une règle 2025 inscrite dans `Year2024Boot::rules()` (ou inversement)
 * cassait silencieusement la conformité au centime près · ce test
 * verrouille les inscriptions croisées.
 */
final class Year2025BootInversionTest extends TestCase
{
    #[Test]
    public function toutes_les_regles_pipeline_2025_ont_fiscal_year_2025(): void
    {
        $boot = new Year2025Boot;

        foreach ($boot->rules() as $class) {
            /** @var FiscalRule $rule */
            $rule = $this->app->make($class);
            $year = $rule->applicabilityStart()->year;

            self::assertSame(
                2025,
                $year,
                sprintf('%s a fiscalYear=%d, attendu 2025.', $class, $year),
            );
        }
    }

    #[Test]
    public function toutes_les_regles_documentaires_2025_ont_fiscal_year_2025(): void
    {
        $boot = new Year2025Boot;

        foreach ($boot->informativeRules() as $class) {
            /** @var FiscalRule $rule */
            $rule = new $class;
            $year = $rule->applicabilityStart()->year;

            self::assertSame(
                2025,
                $year,
                sprintf('%s a fiscalYear=%d, attendu 2025.', $class, $year),
            );
        }
    }

    #[Test]
    public function r2024_017_supprime_n_est_pas_dans_year2025_boot(): void
    {
        // L'exonération hybride conditionnelle R-2024-017 a été supprimée
        // au 01/01/2025 (LF 2024 art. 97). Elle ne doit en aucun cas
        // être inscrite dans le pipeline 2025.
        $boot = new Year2025Boot;

        self::assertNotContains(
            R2024_017_ConditionalHybridExemption::class,
            $boot->rules(),
            'R-2024-017 (hybride conditionnel) doit être absente du pipeline 2025.',
        );
    }

    #[Test]
    public function r2025_033_tai_n_est_pas_dans_year2024_boot(): void
    {
        // La TAI (R-2025-033) est créée par LF 2025 art. 95. Elle ne
        // doit pas être rétro-inscrite dans 2024.
        $boot2024 = new Year2024Boot;
        $allClasses = array_merge($boot2024->rules(), $boot2024->informativeRules());

        self::assertNotContains(
            R2025_033_FleetGreeningIncentiveTax::class,
            $allClasses,
            'R-2025-033 (TAI nouveauté 2025) doit être absente du catalogue 2024.',
        );
    }

    #[Test]
    public function r2025_023_e85_n_est_pas_dans_year2024_boot(): void
    {
        // L'abattement E85 (R-2025-023) est créé par LF 2024 art. 97, 23°
        // au 01/01/2025. Il ne doit pas être rétro-inscrit dans 2024.
        $boot2024 = new Year2024Boot;
        $allClasses = array_merge($boot2024->rules(), $boot2024->informativeRules());

        self::assertNotContains(
            R2025_023_E85Abatement::class,
            $allClasses,
            'R-2025-023 (abattement E85 nouveauté 2025) doit être absent du catalogue 2024.',
        );
    }

    #[Test]
    public function r2024_001_n_est_pas_dans_year2025_boot(): void
    {
        // Garde-fou symétrique · chaque année a sa propre R-YYYY-001
        // (texte CIBS identique mais classe PHP distincte ADR-0022).
        $boot = new Year2025Boot;
        $allClasses = array_merge($boot->rules(), $boot->informativeRules());

        self::assertNotContains(
            R2024_001_TaxpayerAndTriggeringEvent::class,
            $allClasses,
            'R-2024-001 doit être remplacée par R-2025-001 dans le catalogue 2025.',
        );
    }
}

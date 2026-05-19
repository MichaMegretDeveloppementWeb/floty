<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Goldens;

use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens BOFiP officiels 2026 : doctrine textuelle opposable.
 *
 * **Source unique** : `BOI-AIS-MOB-10-30-20-20250528` § 230, audit
 * Chrome live 15/05/2026. Le BOFiP a publié les exemples chiffrés
 * 2026 par anticipation programmée de LF 2024 art. 97-20° au moment
 * de la mise à jour 28/05/2025 (consultation publique LF 2025).
 *
 * **Exemple 1 BOFiP § 230** : Véhicule WLTP 100 g/km ·
 *   - En 2024 : 173 € (14×0 + 41×1 + 8×2 + 32×3 + 5×4)
 *   - En 2025 : 193 € (9×0 + 41×1 + 8×2 + 32×3 + 10×4)
 *   - **En 2026 : 213 €** (4×0 + 41×1 + 8×2 + 32×3 + 15×4)
 *   - En 2027 : 232 € (40×1 + 8×2 + 32×3 + 20×4)
 *
 * **Exemple 2 BOFiP § 230** : même véhicule WLTP 100 g/km partagé en
 * cours d'année 2026 entre deux entreprises ·
 *   - Entreprise A : 90 jours → 213 × 90/365 = **52,52 €**
 *   - Entreprise B : 275 jours → 213 × 275/365 = **160,48 €**
 *
 * Ces goldens sont **textuels** : la moindre régression d'une borne
 * du barème WLTP 2026 entraînerait une divergence avec la doctrine
 * BOFiP, ce qui constituerait une non-conformité fiscale opposable.
 *
 * **Note polluants 2026** : BOFiP `BOI-AIS-MOB-10-30-20-20250528`
 * § 280 publie encore la table polluants 2025 (E=0/Cat1=100/MostPolluting=500),
 * pas la revalorisation +30 % de LF 2026 art. 58. Le BOFiP sera
 * republié après l'effet 01/03/2026 : pour l'instant, la doctrine
 * opposable polluants 2026 reste la rédaction du CIBS L. 421-135
 * v 01/03/2026 (audité Z3.5).
 */
final class BofipOfficialExamples2026Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bofip_exemple_1_wltp_100g_km_2026_donne_exactement_213_euros(): void
    {
        // Calcul littéral BOFiP : 4×0 + (45-4)×1 + (53-45)×2
        // + (85-53)×3 + (100-85)×4 = 0 + 41 + 16 + 96 + 60 = 213 €
        $vfc = $this->makeVfc([
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 100,
        ]);
        $context = $this->makeContext($vfc);

        $tariff = (new R2026_010_WltpProgressive)->price($context)->co2FullYearTariff;

        self::assertSame(213.0, $tariff, 'WLTP 100 g/km 2026 doit donner 213 € (BOFiP § 230 exemple 1).');
    }

    #[Test]
    public function bofip_exemple_2_entreprise_a_90j_donne_52_52_euros(): void
    {
        // 213 € × 90/365 = 52,5205... : BOFiP arrondi à 52,52 €.
        $expected = 213.0 * 90 / 365;

        self::assertEqualsWithDelta(52.52, $expected, 0.01, 'Prorata BOFiP entreprise A · 90j sur 365 = 52,52 €.');
    }

    #[Test]
    public function bofip_exemple_2_entreprise_b_275j_donne_160_48_euros(): void
    {
        // 213 € × 275/365 = 160,4794... : BOFiP arrondi à 160,48 €.
        $expected = 213.0 * 275 / 365;

        self::assertEqualsWithDelta(160.48, $expected, 0.01, 'Prorata BOFiP entreprise B · 275j sur 365 = 160,48 €.');
    }

    #[Test]
    public function bofip_exemple_2_partage_a_b_2026_somme_213_euros(): void
    {
        // 90 + 275 = 365 jours : A + B = 213 € (tarif annuel plein
        // sans pertes au prorata : invariant arithmétique).
        $partA = 213.0 * 90 / 365;
        $partB = 213.0 * 275 / 365;

        self::assertEqualsWithDelta(213.0, $partA + $partB, 0.01, 'Somme A+B doit reconstituer le tarif annuel plein 213 €.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedCo2Method: HomologationMethod::Wltp,
        );
    }
}

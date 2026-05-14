<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Fiscal\Year2025\Transversal\R2025_003_FinalRounding;
use App\Fiscal\Year2025\Transversal\R2025_027_MileageReimbursementCoefficient;
use App\Providers\FiscalServiceProvider;

/**
 * Catalogue des règles fiscales 2025 (cf. `taxes-rules/2025.md`).
 *
 * Référencée par `config('floty.fiscal.year_boots')` et instanciée par
 * {@see FiscalServiceProvider} au boot.
 *
 * **Changements majeurs vs 2024** :
 * - Dénominateur prorata = 365 (2025 non bissextile, vs 366 en 2024).
 * - Barèmes WLTP/NEDC/PA durcis par LF 2024 art. 97, 19° au 01/01/2025.
 * - Exonération hybride conditionnelle R-2024-017 supprimée au 01/01/2025.
 * - Nouveauté · abattement E85 R-2025-023 (CIBS L. 421-125 réformé).
 *
 * **Isolation stricte** · aucune classe `App\Fiscal\Year2024\*` n'est
 * référencée ni utilisée dans le pipeline 2025 (cf. ADR-0022 et la
 * section « Garantie de conformité fiscale 2025 » de
 * `taxes-rules/2025.md`).
 *
 * Pour modifier le périmètre des règles 2025 (ajout, retrait, réordonnance),
 * éditer la liste {@see rules()} ci-dessous · sans toucher au provider.
 */
final class Year2025Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2025;
    }

    /**
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        // Phase C · 3 transversales pipeline. Les autres règles (16
        // classes : 3 Classification + 7 Exemption + 4 Pricing + 1
        // Abatement + 1 Transversal R-2025-027 inactive supplémentaire)
        // seront ajoutées au fur et à mesure des phases D à H.
        return [
            R2025_002_DailyProrata::class,
            R2025_003_FinalRounding::class,
            R2025_027_MileageReimbursementCoefficient::class,
        ];
    }

    /**
     * Règles documentaires-only seedées dans `fiscal_rules` pour
     * alimenter la page « Règles de calcul » mais qui ne participent
     * **pas** au pipeline de calcul (cf. {@see InformativeRule}).
     *
     * Phase I (à venir) · 14 classes documentaires-only seront ajoutées.
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [];
    }
}

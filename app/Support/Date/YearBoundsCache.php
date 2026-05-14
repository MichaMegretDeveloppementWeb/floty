<?php

declare(strict_types=1);

namespace App\Support\Date;

use Carbon\CarbonImmutable;

/**
 * Cache statique process-local des bornes d'année (1er janvier / 31 décembre)
 * sous forme `CarbonImmutable`.
 *
 * Évite de re-parser ou re-construire N fois les mêmes instances dans les hot
 * paths fiscal/facturation (DashboardStatsService, RentalPriceCalculator,
 * BillingCalculator, etc.) qui itèrent sur l'année calendaire complète.
 *
 * Le cache est process-local (pas de cache HTTP partagé), donc il suffit pour
 * la durée d'une requête. `flush()` est exposé pour les tests qui veulent
 * partir d'un état neuf.
 *
 * Cf. plan-remédiation Vague 1 Lot 3 D6 (F-16-009 et consolidés).
 */
final class YearBoundsCache
{
    /** @var array<int, CarbonImmutable> */
    private static array $startCache = [];

    /** @var array<int, CarbonImmutable> */
    private static array $endCache = [];

    public static function start(int $year): CarbonImmutable
    {
        return self::$startCache[$year] ??= CarbonImmutable::create($year, 1, 1, 0, 0, 0);
    }

    public static function end(int $year): CarbonImmutable
    {
        return self::$endCache[$year] ??= CarbonImmutable::create($year, 12, 31, 23, 59, 59);
    }

    /**
     * Vide le cache · utile dans les `setUp` de tests qui veulent partir d'un
     * état neuf (l'instance partagée pourrait sinon polluer les assertions
     * d'identité `===` cross-tests).
     */
    public static function flush(): void
    {
        self::$startCache = [];
        self::$endCache = [];
    }
}

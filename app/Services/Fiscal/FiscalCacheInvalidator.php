<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Centralisation de la stratégie de cache + invalidation pour les
 * valeurs fiscales par véhicule × année (chantier perf 2026-05-17).
 *
 * **Périmètre actuel** · `vehicleFullYearTaxBreakdown(vehicle, year)`
 * de {@see FleetFiscalAggregator}. Cette méthode dépend exclusivement
 * de · (1) les VFC du véhicule sur l'année, (2) la colonne
 * `vehicles.first_origin_registration_date` (R-2024-017), (3) les
 * règles fiscales codées dans `app/Fiscal/Year{YYYY}/`.
 *
 * **Stratégie d'invalidation** · les 3 sources d'inputs sont surveillées ·
 *   1. VFC · Observer Eloquent sur `VehicleFiscalCharacteristics`
 *      (saved + deleted) appelle {@see invalidateForVehicle()}.
 *   2. Vehicle · Observer Eloquent sur `Vehicle` (saved conditionnel
 *      sur `first_origin_registration_date` + deleted + restored +
 *      forceDeleted) appelle {@see invalidateForVehicle()}.
 *   3. Règles fiscales · `php artisan cache:clear` au déploiement
 *      (cf. `deploy.sh` ligne 130) vide tout · gold standard.
 *
 * **Pièges Eloquent couverts manuellement** ·
 *   - 2 bulk deletes dans `VehicleFiscalCharacteristicsWriteRepository`
 *     (`deleteOne` + `deleteVersionsFromDate`) appellent explicitement
 *     {@see invalidateForVehicle()} AVANT le bulk delete · les events
 *     Eloquent ne se déclenchent pas sur les `query()->delete()`.
 *
 * **Filet de sécurité TTL** · {@see CACHE_TTL_SECONDS} (1h). Couvre
 * tout vecteur inattendu (raw SQL futur, manipulation tinker, etc.).
 *
 * **Plage d'années invalidées** · `[YEAR_MIN..currentYear + 1]` à
 * chaque invalidation par véhicule (~4-5 forgets par invalidation).
 * Choisi pour éviter le maintien d'un index inverse complexe · le
 * surcoût (4 forgets vs lookup index) est négligeable.
 *
 * **Documentation associée** · `app/Fiscal/README.md` (interdiction
 * raw SQL + procédure manuelle si nécessaire) + section dans
 * `project-management/recherches-fiscales/methodologie.md`.
 */
final readonly class FiscalCacheInvalidator
{
    /**
     * TTL filet de sécurité (1 heure). Choisi court pour qu'une
     * éventuelle invalidation oubliée ne reste pas plus d'1h.
     * Si confiance acquise + monitoring OK · remonter à 24h+.
     */
    public const int CACHE_TTL_SECONDS = 3600;

    /**
     * Année minimum couverte par l'invalidation par véhicule.
     * Aligné sur le 1er millésime fiscal supporté par le moteur
     * (`app/Fiscal/Year2024/`).
     */
    public const int YEAR_MIN = 2024;

    public function __construct(
        private CacheRepository $cache,
    ) {}

    /**
     * Clé canonique pour le cache `vehicleFullYearTaxBreakdown`.
     * Format · `fiscal:vfytb:{vehicleId}:{year}` · préfixe `fiscal:`
     * pour le namespacing visuel en BDD, court pour limiter le poids
     * de l'index `cache.key`.
     */
    public static function cacheKeyForBreakdown(int $vehicleId, int $year): string
    {
        return sprintf('fiscal:vfytb:%d:%d', $vehicleId, $year);
    }

    /**
     * Invalide toutes les clés cachées pour un véhicule sur la plage
     * d'années supportées par le moteur fiscal. Idempotent · `forget`
     * sur une clé absente est un no-op.
     */
    public function invalidateForVehicle(int $vehicleId): void
    {
        $currentYear = (int) CarbonImmutable::now()->year;
        // +1 pour couvrir l'année N+1 (prévisions / déclarations en
        // avance). Si elle n'est pas en cache · no-op.
        for ($year = self::YEAR_MIN; $year <= $currentYear + 1; $year++) {
            $this->cache->forget(self::cacheKeyForBreakdown($vehicleId, $year));
        }
    }
}

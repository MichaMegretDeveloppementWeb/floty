<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;

/**
 * Précharge en 1 SQL les réductions actives pour une (entreprise × année)
 * ou un batch multi-entreprises, et construit un {@see ResolvedDiscountIndex}
 * exploitable en lookup O(1) par le {@see DiscountApplier}.
 *
 * Service stateless · enregistré singleton via `AppServiceProvider`
 * (ou injecté directement via le container Laravel · les repos sont
 * déjà singletons).
 */
final readonly class DiscountResolver
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * 1 SQL · réductions actives pour (entreprise × année). Eager-load
     * `vehicles`.
     */
    public function preloadForCompanyYear(int $companyId, int $year): ResolvedDiscountIndex
    {
        $discounts = $this->reader->findActiveForCompanyYear($companyId, $year);

        return ResolvedDiscountIndex::fromDiscounts($discounts);
    }

    /**
     * 1 SQL · réductions actives pour N entreprises sur une année.
     * Retourne un index PAR entreprise (chaque entreprise a son propre
     * `ResolvedDiscountIndex`) pour préserver l'isolation.
     *
     * Utilisé par les batches multi-cies (Dashboard `totalRecettesForYears`).
     *
     * @param  list<int>  $companyIds
     * @return array<int, ResolvedDiscountIndex> companyId → index
     */
    public function preloadForCompaniesYear(array $companyIds, int $year): array
    {
        if ($companyIds === []) {
            return [];
        }

        $all = $this->reader->findActiveForCompaniesYear($companyIds, $year);

        // Regroupe par company_id.
        $byCompany = [];
        foreach ($all as $discount) {
            $byCompany[(int) $discount->company_id][] = $discount;
        }

        // Construit un index par company, même vide (pour les company
        // sans réduction · permet au consommateur d'appeler isEmpty()
        // sans test null préalable).
        $result = [];
        foreach ($companyIds as $companyId) {
            $result[$companyId] = ResolvedDiscountIndex::fromDiscounts(
                $byCompany[$companyId] ?? [],
            );
        }

        return $result;
    }
}

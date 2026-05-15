<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\User\Billing\PendingInvoiceYearData;
use Carbon\CarbonImmutable;

/**
 * Résout, pour une entreprise donnée, la liste des années où il reste
 * des factures mensuelles à générer (Phase D5.10.S).
 *
 * Symétrique de {@see App\Services\Fiscal\Declaration\PendingDeclarationsResolver}
 * côté facturation · alimente l'encart « À faire » de l'onglet Vue
 * d'ensemble de la fiche entreprise.
 *
 * Granularité · 1 entrée par année (pas par mois). Le détail mois par
 * mois reste accessible via l'onglet Facturation. L'encart se contente
 * de signaler « N factures à générer pour YYYY ».
 *
 * --------------------------------------------------------------------
 * Service consommateur · calcul non matérialisé.
 * --------------------------------------------------------------------
 *
 * Performance · ce service recalcule à chaque appel l'agrégat
 * « factures mensuelles à générer » à partir des tables `contracts`,
 * `vehicle_yearly_pricings`, `invoices`. Pour chaque année couverte par
 * au moins un contrat de l'entreprise · 12 appels mensuels à
 * {@see BillingBreakdownService::byCompanyForYear} (lui-même 2 SQL +
 * agrégation in-memory cf. perf C5.b).
 *
 * Coût empirique observé V1 · ~50-150ms pour 1-5 companies × 1-3 années
 * en local Herd. Acceptable pour l'usage actuel (encart « À faire »
 * affiché à la visite Show entreprise, fréquence faible). Le pipeline
 * fiscal interne déjà mémoïse ses sous-calculs intra-requête.
 *
 * Doctrine V1 · matérialisation différée (cf. plan-remediation Lot 3
 * § F-11P-002 + `implementation-rules/performance.md` § 3).
 *
 * Seuils de déclenchement de la matérialisation ·
 * - Latence perçue > 500ms sur Show entreprise, OU
 * - Plus de 50 companies actives ayant des contrats sur > 3 années, OU
 * - Plus de 10 appels concurrents observés en logs (forecast V2/V3).
 *
 * Pattern de matérialisation à appliquer le jour venu ·
 * 1. Créer table `pending_invoices_cache` (company_id, year, count,
 *    computed_at, payload_json) avec PK composite (company_id, year).
 * 2. Créer Observer sur `Contract` + `VehicleYearlyPricing` + `Invoice`
 *    qui invalide les lignes (company_id, year) impactées à save/delete.
 * 3. Remplacer le calcul live par lecture cache · si miss, fallback
 *    sur le calcul actuel et persiste en cache.
 * 4. Tests · invalidation Observer + cache hit/miss + équivalence
 *    ancien algo sur ≥3 jeux de données représentatifs.
 */
final readonly class PendingInvoicesResolver
{
    public function __construct(
        private BillingBreakdownService $breakdown,
        private ContractReadRepositoryInterface $contracts,
    ) {}

    /**
     * @return list<PendingInvoiceYearData>
     */
    public function pendingForCompany(int $companyId): array
    {
        $contractYears = $this->yearsCoveredByContractsForCompany($companyId);
        if ($contractYears === []) {
            return [];
        }

        $now = CarbonImmutable::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $pending = [];
        foreach ($contractYears as $year) {
            $breakdown = $this->breakdown->byCompanyForYear($companyId, $year);

            $count = 0;
            foreach ($breakdown->entries as $entry) {
                // Mois éligibles à génération :
                //   - utilisation effective (daysUsed > 0)
                //   - tarif présent (sinon l'utilisateur doit d'abord
                //     corriger sur la fiche véhicule)
                //   - pas encore facturé (pas de row Invoice active)
                //   - mois écoulé (le mois en cours ou futur ne se
                //     facture pas tant qu'il n'est pas clos)
                if ($entry->daysUsed <= 0) {
                    continue;
                }
                if ($entry->hasMissingPricing) {
                    continue;
                }
                if ($entry->existingInvoiceId !== null) {
                    continue;
                }

                $isPastMonth = $year < $currentYear
                    || ($year === $currentYear && $entry->month < $currentMonth);
                if (! $isPastMonth) {
                    continue;
                }

                $count++;
            }

            if ($count > 0) {
                $pending[] = new PendingInvoiceYearData(
                    fiscalYear: $year,
                    missingInvoicesCount: $count,
                );
            }
        }

        usort(
            $pending,
            static fn (PendingInvoiceYearData $a, PendingInvoiceYearData $b): int => $a->fiscalYear <=> $b->fiscalYear,
        );

        return $pending;
    }

    /**
     * Mêmes années que le `PendingDeclarationsResolver` · plage couverte
     * par les contrats existants de l'entreprise.
     *
     * Lot 4 D05 (F-11P-001) · délègue au `ContractReadRepository` qui
     * porte cette query (conformité ADR-0013 R3 · pas de SQL direct
     * dans les Services). Le scope SoftDeletes du model Contract est
     * appliqué automatiquement par Eloquent (équivalent au
     * `whereNull('deleted_at')` qu'on faisait en `DB::table` auparavant).
     *
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        return $this->contracts->findActiveYearsForCompany($companyId);
    }
}

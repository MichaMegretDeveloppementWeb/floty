<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Service applicatif chargé de marquer les factures impactées par une
 * mutation comme `is_divergent = true` (T6 / Phase 14.R V1.2).
 *
 * **Pourquoi un service dédié** : centraliser la logique d'énumération
 * (year, month) et de bulk UPDATE pour qu'elle soit réutilisable par
 * les 3 observers (Contract, VehicleYearlyPricing, Vehicle.exit_date)
 * sans duplication. Conforme ADR-0013 : zéro SQL ailleurs, écritures
 * via Eloquent / Query Builder.
 *
 * **Doctrine immuabilité** (ADR-0008) : `is_divergent` est une
 * métadonnée d'observabilité, pas un champ du snapshot. Les colonnes
 * figées (`total_ht_cents`, `pdf_path`, `pdf_hash`, `invoice_number`,
 * `invoice_lines`) ne sont jamais mutées par ce service.
 *
 * **Coût** : un appel = un (rarement deux ou trois) bulk UPDATE Eloquent
 * conditionnel sur `(company_id, year, month)`. Aucun appel à
 * `BillingCalculator`. L'intérêt principal : faire payer la divergence
 * à l'écriture (rare) plutôt qu'à la lecture (à chaque Index).
 */
final readonly class InvoiceDivergenceFlagger
{
    /**
     * Marque divergentes les factures de l'entreprise dont (year, month)
     * tombe dans le range courant ou l'éventuel range précédent (cas
     * Update Contract avec dates modifiées).
     *
     * Retourne le nombre de lignes flippées (utile pour les tests ; en
     * pratique on n'agit pas sur le retour).
     */
    public function flagForContractRange(
        int $companyId,
        string $rangeStart,
        string $rangeEnd,
        ?string $previousRangeStart = null,
        ?string $previousRangeEnd = null,
    ): int {
        $tuples = $this->expandRange($rangeStart, $rangeEnd);
        if ($previousRangeStart !== null && $previousRangeEnd !== null) {
            $tuples = $this->deduplicate(array_merge(
                $tuples,
                $this->expandRange($previousRangeStart, $previousRangeEnd),
            ));
        }

        if ($tuples === []) {
            return 0;
        }

        return $this->bulkUpdateForCompanyTuples($companyId, $tuples);
    }

    /**
     * Marque divergentes les factures de l'année du tarif modifié, pour
     * toutes les entreprises ayant eu un contrat sur ce véhicule chevauchant
     * l'année.
     */
    public function flagForVehiclePricingYear(int $vehicleId, int $year): int
    {
        return Invoice::query()
            ->where('year', $year)
            ->where('is_divergent', false)
            ->whereIn('company_id', function (QueryBuilder $sub) use ($vehicleId, $year): void {
                $sub->select('company_id')->distinct()
                    ->from('contracts')
                    ->where('vehicle_id', $vehicleId)
                    ->where('start_date', '<=', "{$year}-12-31")
                    ->where('end_date', '>=', "{$year}-01-01")
                    ->whereNull('deleted_at');
            })
            ->update(['is_divergent' => true]);
    }

    /**
     * Marque divergentes toutes les factures correspondant à un contrat
     * existant du véhicule (cas Vehicle.exit_date qui clip la facturation,
     * cf. T5 / ADR-0018).
     */
    public function flagForVehicle(int $vehicleId): int
    {
        $contracts = DB::table('contracts')
            ->where('vehicle_id', $vehicleId)
            ->whereNull('deleted_at')
            ->select('company_id', 'start_date', 'end_date')
            ->get();

        /** @var array<int, list<array{year:int,month:int}>> $byCompany */
        $byCompany = [];
        foreach ($contracts as $contract) {
            $companyId = (int) $contract->company_id;
            foreach ($this->expandRange((string) $contract->start_date, (string) $contract->end_date) as $tuple) {
                $byCompany[$companyId][] = $tuple;
            }
        }

        $total = 0;
        foreach ($byCompany as $companyId => $tuples) {
            $total += $this->bulkUpdateForCompanyTuples($companyId, $this->deduplicate($tuples));
        }

        return $total;
    }

    /**
     * Bulk UPDATE Eloquent sur les factures de l'entreprise dont
     * (year, month) match l'un des tuples. Skippe celles déjà divergentes
     * (idempotence et économie d'écriture).
     *
     * @param  list<array{year:int,month:int}>  $tuples
     */
    private function bulkUpdateForCompanyTuples(int $companyId, array $tuples): int
    {
        if ($tuples === []) {
            return 0;
        }

        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('is_divergent', false)
            ->where(function ($q) use ($tuples): void {
                foreach ($tuples as $tuple) {
                    $q->orWhere(function ($sub) use ($tuple): void {
                        $sub->where('year', $tuple['year'])
                            ->where('month', $tuple['month']);
                    });
                }
            })
            ->update(['is_divergent' => true]);
    }

    /**
     * Énumère tous les couples (year, month) couverts par le range
     * inclusif `[start, end]`. Retourne au moins une entrée si
     * `start <= end`.
     *
     * @return list<array{year:int,month:int}>
     */
    private function expandRange(string $start, string $end): array
    {
        $cursor = CarbonImmutable::parse($start)->startOfMonth();
        $endCarbon = CarbonImmutable::parse($end);

        if ($cursor->isAfter($endCarbon)) {
            return [];
        }

        $tuples = [];
        while ($cursor->lessThanOrEqualTo($endCarbon)) {
            $tuples[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $tuples;
    }

    /**
     * Déduplique une liste de tuples sur la clé `"{year}-{month}"`.
     *
     * @param  list<array{year:int,month:int}>  $tuples
     * @return list<array{year:int,month:int}>
     */
    private function deduplicate(array $tuples): array
    {
        $seen = [];
        $deduped = [];
        foreach ($tuples as $tuple) {
            $key = "{$tuple['year']}-{$tuple['month']}";
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $tuple;
            }
        }

        return $deduped;
    }
}

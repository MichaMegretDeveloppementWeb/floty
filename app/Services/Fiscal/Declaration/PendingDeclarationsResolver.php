<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\PendingDeclarationData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Résout la liste des `(company, year)` pour lesquels une déclaration
 * fiscale est attendue mais pas encore finalisée (Phase 11 D4).
 *
 * **Règle métier** : pour une entreprise donnée, on attend une
 * déclaration pour chaque année **passée** (year < currentYear) où il
 * existe au moins un contrat couvrant cette année, et où il n'existe
 * pas de déclaration `generated` active (`is_obsolete = false`).
 *
 * **Année en cours exclue** : la déclaration N peut techniquement être
 * préparée à tout moment, mais elle n'est « due » qu'à partir de N+1.
 * On n'alerte donc pas pendant l'année en cours.
 *
 * **Deadline** : 30 avril N+1 (CIBS officiel France). `isOverdue` est
 * dérivé via comparaison avec `now()`.
 *
 * Sortie triée plus ancienne année en premier (les retards les plus
 * critiques en haut de l'alerte UI).
 */
final readonly class PendingDeclarationsResolver
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    /**
     * @return list<PendingDeclarationData>
     */
    public function pendingForCompany(int $companyId): array
    {
        $contractYears = $this->yearsCoveredByContractsForCompany($companyId);
        if ($contractYears === []) {
            return [];
        }

        $now = CarbonImmutable::now();
        $currentYear = $now->year;

        $pending = [];
        foreach ($contractYears as $year) {
            // L'année en cours n'est pas due tant qu'elle n'est pas
            // close.
            if ($year >= $currentYear) {
                continue;
            }

            $active = $this->declarations->findActiveForCompanyYear($companyId, $year);
            if ($active !== null && $active->status->value === 'generated') {
                continue;
            }

            $deadline = sprintf('%04d-04-30', $year + 1);
            $isOverdue = $now->isAfter(CarbonImmutable::parse($deadline));

            $pending[] = new PendingDeclarationData(
                fiscalYear: $year,
                deadline: $deadline,
                isOverdue: $isOverdue,
            );
        }

        // Plus ancienne année en premier (le retard le plus critique
        // remonte en haut de l'alerte UI).
        usort(
            $pending,
            static fn (PendingDeclarationData $a, PendingDeclarationData $b): int => $a->fiscalYear <=> $b->fiscalYear,
        );

        return $pending;
    }

    /**
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        $rows = DB::table('contracts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select(['start_date', 'end_date'])
            ->get();

        $years = [];
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse((string) $row->start_date);
            $end = CarbonImmutable::parse((string) $row->end_date);

            for ($y = $start->year; $y <= $end->year; $y++) {
                $years[$y] = true;
            }
        }

        $list = array_keys($years);
        sort($list);

        return $list;
    }
}

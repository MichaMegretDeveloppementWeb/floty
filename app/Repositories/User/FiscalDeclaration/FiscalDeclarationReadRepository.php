<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class FiscalDeclarationReadRepository implements FiscalDeclarationReadRepositoryInterface
{
    public function findById(int $id): ?FiscalDeclaration
    {
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->find($id);
    }

    public function findActiveForCompanyYear(int $companyId, int $year): ?FiscalDeclaration
    {
        // Lot 5 D3 · `orderByDesc('id')` garantit le déterminisme dans
        // le cas pathologique où 2 lignes actives co-existeraient pour le
        // même couple. En production cet état est désormais impossible
        // par construction grâce à l'index unique partial introduit par
        // la migration `add_unique_index_to_fiscal_declarations` (colonne
        // virtuelle MySQL 8 `active_uniqueness_key`), mais on maintient
        // l'ordre côté repo comme défense en profondeur (et pour les
        // tests qui pourraient encore créer des doublons via factory en
        // bypassant la contrainte par erreur).
        //
        // Eager loading `company` aligné sur `findCurrentForCompanyYear`
        // pour éviter un éventuel N+1 chez les consumers.
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->where('is_obsolete', false)
            ->orderByDesc('id')
            ->first();
    }

    public function findCurrentForCompanyYear(int $companyId, int $year): ?FiscalDeclaration
    {
        // « Head » de la chaîne · dernier maillon, la déclaration qui
        // ne pointe vers aucune autre via `superseded_by_id` (donc
        // n'a pas encore été remplacée). Une déclaration obsolète
        // orpheline (S6) reste « head » jusqu'à ce qu'un Draft de
        // régénération soit créé et lui assigne `superseded_by_id`,
        // moment où le Draft devient la nouvelle head (S7).
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->whereNull('superseded_by_id')
            ->orderByDesc('id')
            ->first();
    }

    public function findPredecessorOf(int $declarationId): ?FiscalDeclaration
    {
        // Déclaration X telle que X.superseded_by_id = $declarationId.
        // En pratique 1 seule (chaîne linéaire 1 ancien → 1 nouveau).
        return FiscalDeclaration::query()
            ->with(['company:id,short_code,legal_name,color'])
            ->where('superseded_by_id', $declarationId)
            ->orderByDesc('id')
            ->first();
    }

    public function findHistoryForCompanyYear(int $companyId, int $year): Collection
    {
        return FiscalDeclaration::query()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->orderBy('id')
            ->get();
    }

    public function paginateForIndex(DeclarationIndexQueryData $query): LengthAwarePaginator
    {
        return $this->buildIndexQuery($query)->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function existsAny(): bool
    {
        return FiscalDeclaration::query()->exists();
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array
    {
        /** @var object{min_year: int|null, max_year: int|null}|null $row */
        $row = FiscalDeclaration::query()
            ->selectRaw('MIN(fiscal_year) as min_year, MAX(fiscal_year) as max_year')
            ->first();

        if ($row === null || $row->min_year === null) {
            return null;
        }

        return [
            'min' => (int) $row->min_year,
            'max' => (int) $row->max_year,
        ];
    }

    public function findCompanyOptions(): Collection
    {
        return Company::query()
            ->whereIn('id', FiscalDeclaration::query()->select('company_id'))
            ->orderBy('short_code')
            ->get();
    }

    /**
     * @return Builder<FiscalDeclaration>
     */
    private function buildIndexQuery(DeclarationIndexQueryData $query): Builder
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = FiscalDeclaration::query()
            ->select('fiscal_declarations.*')
            ->with([
                'company:id,short_code,legal_name,color',
                // Phase 11 D5.8.5 · charge le successeur pour pouvoir
                // distinguer S6 (obsolète orphan) de S7 (Draft chaîné)
                // dans la pill statut de l'Index. Phase 13 D5.10.F · on
                // ajoute la `reference` pour rendre les sous-mentions
                // « Régénération en cours · DECL-XXX » et « Remplacée
                // par DECL-XXX ».
                'supersededBy:id,status,reference',
                // Phase 13 D5.10.F · charge le predecessor pour rendre
                // la sous-mention « Remplace DECL-XXX » côté Index.
                'supersedes:id,status,reference,superseded_by_id',
            ]);

        if ($query->companyId !== null) {
            $eloquentQuery->where('fiscal_declarations.company_id', $query->companyId);
        }
        if ($query->fiscalYear !== null) {
            $eloquentQuery->where('fiscal_declarations.fiscal_year', $query->fiscalYear);
        }
        if ($query->status !== null) {
            $eloquentQuery->where('fiscal_declarations.status', $query->status->value);
        }
        if ($query->obsoleteOnly) {
            $eloquentQuery->where('fiscal_declarations.is_obsolete', true);
        }

        // Phase 13 D5.10.F + D5.10.H · recherche par référence avec
        // expansion de chaîne (algorithme 2-branches). L'utilisateur
        // tape une référence (fragment de DECL-XXX-YYYY-NNNN) OU un
        // label brouillon (« Brouillon #4 » / « #4 » / « 4 ») · on
        // identifie les couples `(company_id, fiscal_year)` qui
        // matchent, puis on retourne **toutes** les déclarations de
        // ces couples (expansion chaîne).
        //
        // Branche Q1 · LIKE %term% sur la colonne reference. Le
        // domaine fiscal Floty est structurellement borné (~300
        // lignes max sur 10 ans pour un client), donc le full scan
        // est sub-100ms. Pas de FULLTEXT, pas de dette · c'est le bon
        // choix architectural pour ce domaine.
        //
        // Branche Q2 · si `term` matche un pattern brouillon, on fait
        // un lookup PK direct (instantané) sur l'id extrait.
        if ($query->search !== null && trim($query->search) !== '') {
            $term = trim($query->search);

            // Q1 · couples avec une reference matchante. `toBase()` pour
            // que `merge()` utilise la déduplication d'Illuminate Collection
            // (sur clés) au lieu de l'Eloquent Collection (sur PK · qui
            // exige `id` dans le select).
            $pairsFromReference = FiscalDeclaration::query()
                ->whereNotNull('reference')
                ->where('reference', 'LIKE', '%'.$term.'%')
                ->select('company_id', 'fiscal_year')
                ->distinct()
                ->get()
                ->toBase();

            $pairsFromId = collect();
            if (preg_match('/^(?:[Bb]rouillon\s*)?#?(\d+)$/', $term, $matches) === 1) {
                $extractedId = (int) $matches[1];
                $pairsFromId = FiscalDeclaration::query()
                    ->whereKey($extractedId)
                    ->select('company_id', 'fiscal_year')
                    ->get()
                    ->toBase();
            }

            $pairs = $pairsFromReference
                ->merge($pairsFromId)
                ->unique(fn ($p): string => $p->company_id.'|'.$p->fiscal_year);

            if ($pairs->isEmpty()) {
                $eloquentQuery->whereRaw('1 = 0');
            } else {
                $eloquentQuery->where(function ($q) use ($pairs): void {
                    foreach ($pairs as $pair) {
                        $q->orWhere(function ($q2) use ($pair): void {
                            $q2->where('fiscal_declarations.company_id', $pair->company_id)
                                ->where('fiscal_declarations.fiscal_year', $pair->fiscal_year);
                        });
                    }
                });
            }
        }

        match ($query->sortKey) {
            'company' => $eloquentQuery
                ->leftJoin('companies', 'fiscal_declarations.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'fiscalYear' => $eloquentQuery->orderBy('fiscal_declarations.fiscal_year', $direction),
            'reference' => $eloquentQuery->orderBy('fiscal_declarations.reference', $direction),
            'status' => $eloquentQuery->orderBy('fiscal_declarations.status', $direction),
            'generatedAt' => $eloquentQuery->orderBy('fiscal_declarations.generated_at', $direction),
            // Défaut : plus récente en premier (year DESC, company ASC).
            default => $eloquentQuery
                ->orderByDesc('fiscal_declarations.fiscal_year')
                ->orderBy('fiscal_declarations.company_id')
                ->orderBy('fiscal_declarations.id'),
        };

        return $eloquentQuery;
    }

    public function findGeneratedForCompanyYears(array $companyIds, array $years): Collection
    {
        if ($companyIds === [] || $years === []) {
            return new Collection;
        }

        return FiscalDeclaration::query()
            ->with('company:id,short_code,legal_name')
            ->whereIn('company_id', $companyIds)
            ->whereIn('fiscal_year', $years)
            ->where('status', FiscalDeclarationStatus::Generated)
            ->get();
    }

    public function countWithTrashedForReference(int $companyId, int $year): int
    {
        return FiscalDeclaration::withTrashed()
            ->where('company_id', $companyId)
            ->where('fiscal_year', $year)
            ->whereNotNull('reference')
            ->lockForUpdate()
            ->count();
    }
}

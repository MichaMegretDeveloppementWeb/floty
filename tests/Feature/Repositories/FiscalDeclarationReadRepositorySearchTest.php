<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Data\Shared\Listing\SortDirection;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'algorithme search 2-queries avec expansion de chaîne
 * implémenté dans `FiscalDeclarationReadRepository::buildIndexQuery`.
 */
final class FiscalDeclarationReadRepositorySearchTest extends TestCase
{
    use RefreshDatabase;

    private FiscalDeclarationReadRepository $repo;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(FiscalDeclarationReadRepository::class);
        $this->company = Company::factory()->create(['short_code' => 'ACM']);
    }

    #[Test]
    public function find_current_for_company_years_equivaut_aux_appels_annuels(): void
    {
        // 2024 · chaîne obsolète → draft (head = le draft de tête).
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)->forYear(2024)->obsolete()->create();
        $head2024 = FiscalDeclaration::factory()
            ->forCompany($this->company)->forYear(2024)->draft()->create();
        $obsolete->update(['superseded_by_id' => $head2024->id]);

        // 2025 · une déclaration générée (head). 2026 · aucune.
        $head2025 = FiscalDeclaration::factory()
            ->forCompany($this->company)->forYear(2025)->generated()->create();

        $years = [2024, 2025, 2026];

        DB::enableQueryLog();
        DB::flushQueryLog();
        $batch = $this->repo->findCurrentForCompanyYears($this->company->id, $years);
        $batchQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach ($years as $year) {
            $single = $this->repo->findCurrentForCompanyYear($this->company->id, $year);
            self::assertSame(
                $single?->id,
                ($batch[$year] ?? null)?->id,
                "année {$year} · head de chaîne identique au lookup unitaire",
            );
        }

        self::assertSame($head2024->id, $batch[2024]->id, '2024 · head = draft de tête');
        self::assertSame($head2025->id, $batch[2025]->id);
        self::assertArrayNotHasKey(2026, $batch, '2026 sans déclaration → absente du map');

        // 1 requête déclarations (+ 1 eager company) quel que soit le
        // nombre d'années, vs 1 lookup + 1 eager par année.
        self::assertLessThanOrEqual(2, $batchQueries, 'batch = requêtes constantes, pas de N+1 par année');
    }

    #[Test]
    public function search_etend_a_toute_la_chaine_du_couple(): void
    {
        // Chaîne : obsolète DECL-ACM-2024-0001 → draft sans référence
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->obsolete()
            ->create(['reference' => 'DECL-ACM-2024-0001']);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->draft()
            ->create(['reference' => null]);

        $obsolete->update(['superseded_by_id' => $draft->id]);

        // Autre couple non concerné
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2025-0001']);

        $query = $this->buildQuery(search: '2024-0001');
        $paginator = $this->repo->paginateForIndex($query);

        // L'utilisateur tape « 2024-0001 », il voit les 2 déclarations
        // du couple (2024) · l'obsolète qui matche directement, ET le
        // draft sans référence qui partage le même couple (expansion
        // de chaîne).
        self::assertSame(2, $paginator->total());
        $ids = collect($paginator->items())->pluck('id')->all();
        self::assertContains($obsolete->id, $ids);
        self::assertContains($draft->id, $ids);
    }

    #[Test]
    public function search_sans_match_retourne_set_vide(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2024-0001']);

        $query = $this->buildQuery(search: 'INEXISTANT-XYZ');
        $paginator = $this->repo->paginateForIndex($query);

        self::assertSame(0, $paginator->total());
    }

    #[Test]
    public function search_vide_retourne_toutes_les_declarations(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2024-0001']);

        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2025-0001']);

        $query = $this->buildQuery(search: null);
        $paginator = $this->repo->paginateForIndex($query);

        self::assertSame(2, $paginator->total());
    }

    #[Test]
    public function search_seulement_espaces_traite_comme_vide(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2024-0001']);

        $query = $this->buildQuery(search: '   ');
        $paginator = $this->repo->paginateForIndex($query);

        self::assertSame(1, $paginator->total());
    }

    #[Test]
    public function search_combine_avec_filtre_status(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->obsolete()
            ->create(['reference' => 'DECL-ACM-2024-0001']);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2024)
            ->draft()
            ->create(['reference' => null]);

        // Search étend à toute la chaîne du couple 2024, MAIS le filtre
        // status=draft restreint au draft seul.
        $query = $this->buildQuery(
            search: '2024-0001',
            status: FiscalDeclarationStatus::Draft,
        );
        $paginator = $this->repo->paginateForIndex($query);

        self::assertSame(1, $paginator->total());
        self::assertSame($draft->id, $paginator->items()[0]->id);
    }

    private function buildQuery(
        ?string $search = null,
        ?FiscalDeclarationStatus $status = null,
    ): DeclarationIndexQueryData {
        return new DeclarationIndexQueryData(
            companyId: null,
            fiscalYear: null,
            status: $status,
            obsoleteOnly: false,
            page: 1,
            perPage: 50,
            search: $search,
            sortKey: null,
            sortDirection: SortDirection::Desc,
        );
    }
}

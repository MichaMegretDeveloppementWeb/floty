<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lot 5 D3 (F-19-006) · garanties autour de l'invariant
 * « au plus 1 déclaration active par couple `(company_id, fiscal_year)` ».
 *
 * Couvre ·
 *   - L'index unique partial (colonne virtuelle MySQL 8
 *     `active_uniqueness_key`) qui empêche la création d'une 2ᵉ
 *     déclaration active pour le même couple.
 *   - La tolérance soft-delete · supprimer (soft) une déclaration
 *     active doit autoriser la création d'une nouvelle pour le même
 *     couple (la colonne virtuelle vaut NULL pour les soft-deleted).
 *   - La tolérance obsolescence · marquer une déclaration obsolète
 *     doit autoriser la création d'une nouvelle (la colonne virtuelle
 *     vaut NULL pour les obsoletes).
 *   - Le combiné soft-delete + obsolète.
 *
 * Note · l'ordre déterministe `orderByDesc('id')` ajouté à
 * `findActiveForCompanyYear` reste testé indirectement par les autres
 * tests de la suite qui consomment cette méthode · l'invariant garanti
 * par l'index unique rend impossible de tester l'ordre sur un état à
 * 2 actives concurrentes (la contrainte rejetterait l'insert).
 */
final class FiscalDeclarationActiveUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function index_unique_empeche_2_declarations_actives_pour_meme_couple(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/decl_active_uniqueness|Duplicate entry/');

        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();
    }

    #[Test]
    public function index_unique_tolere_soft_delete_pour_recreer_une_active(): void
    {
        $first = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();

        $first->delete();

        // Après soft-delete, la colonne virtuelle `active_uniqueness_key`
        // vaut NULL pour `$first` (deleted_at IS NOT NULL), donc le
        // couple `(company, 2026)` est de nouveau disponible.
        $second = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();

        self::assertNotSame($first->id, $second->id);
        self::assertNotNull($second->fresh());
    }

    #[Test]
    public function index_unique_tolere_obsolete_pour_recreer_une_active(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->obsolete()
            ->create();

        // Après bascule en obsolete, la colonne virtuelle vaut NULL
        // (is_obsolete = 1), donc une nouvelle active peut coexister
        // pour le même couple. Cas d'usage · régénération volontaire
        // d'une Generated obsolète (cf. RegenerateDeclarationAction).
        $regen = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();

        self::assertTrue($obsolete->fresh()->is_obsolete);
        self::assertFalse($regen->fresh()->is_obsolete);
    }

    #[Test]
    public function index_unique_tolere_soft_delete_et_obsolete_combinés(): void
    {
        // Cas combiné · une obsolète qui est ensuite soft-deleted ·
        // les 2 conditions individuelles libèrent la clé d'unicité.
        $first = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->obsolete()
            ->create();
        $first->delete();

        $second = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2026)
            ->draft()
            ->create();

        self::assertNotSame($first->id, $second->id);
    }
}

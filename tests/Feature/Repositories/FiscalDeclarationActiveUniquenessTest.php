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
 * Garanties autour de l'invariant « au plus 1 déclaration active par couple
 * `(company_id, fiscal_year)` » via l'index unique partial sur la colonne
 * virtuelle MySQL 8 `active_uniqueness_key`. Couvre l'index, la tolérance
 * soft-delete, la tolérance obsolescence et le combiné.
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fou anti-régression : les flags `is_oig` et
 * `is_individual_business` ne doivent **pas** être mass-assignables tant
 * que les règles d'exonération R-2024-018 et R-2024-019 sont des stubs
 * (cf. `R2024_018And019_StubExemptionsTest`).
 *
 * Si quelqu'un les ré-ajoute au `Fillable` sans implémenter les règles
 * fiscales, ce test casse immédiatement et force la PR à expliciter le
 * choix (audit produit du 2026-05-04 § D2).
 *
 * **Pour réactiver** : implémenter R-2024-018 / R-2024-019, supprimer le
 * test stub correspondant, puis remettre les deux flags dans le
 * `Fillable` de `app/Models/Company.php`.
 */
final class CompanyOigEirlMassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function is_oig_n_est_pas_dans_le_fillable(): void
    {
        $company = new Company;

        self::assertNotContains(
            'is_oig',
            $company->getFillable(),
            'is_oig doit rester hors Fillable tant que R-2024-018 (OIG) est un stub.',
        );
    }

    #[Test]
    public function is_individual_business_n_est_pas_dans_le_fillable(): void
    {
        $company = new Company;

        self::assertNotContains(
            'is_individual_business',
            $company->getFillable(),
            'is_individual_business doit rester hors Fillable tant que R-2024-019 (EIRL) est un stub.',
        );
    }

    #[Test]
    public function mass_assignment_de_is_oig_via_create_leve_une_exception(): void
    {
        // Floty active `Model::preventSilentlyDiscardingAttributes()` :
        // un attribut hors Fillable passé en mass-assignment lève une
        // `MassAssignmentException` au lieu d'être silently ignoré.
        // Garantit qu'un appel oublieux côté Action / Controller ne
        // peut pas placer une entreprise en OIG/EIRL silencieusement.
        $this->expectException(MassAssignmentException::class);

        Company::create([
            'legal_name' => 'Test SARL',
            'short_code' => 'TST',
            'color' => 'indigo',
            'is_oig' => true,
        ]);
    }

    #[Test]
    public function mass_assignment_de_is_individual_business_via_create_leve_une_exception(): void
    {
        $this->expectException(MassAssignmentException::class);

        Company::create([
            'legal_name' => 'Test SARL',
            'short_code' => 'TST',
            'color' => 'indigo',
            'is_individual_business' => true,
        ]);
    }

    #[Test]
    public function la_factory_force_les_deux_flags_a_false(): void
    {
        // Garde-fou complémentaire : un seeder qui crée 100 companies
        // ne doit jamais en produire une OIG/EIRL par défaut. Pour
        // tester ces cas, override explicite via state.
        $company = Company::factory()->create();

        self::assertFalse($company->is_oig);
        self::assertFalse($company->is_individual_business);
    }
}

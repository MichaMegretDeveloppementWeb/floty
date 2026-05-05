<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\User\Contract;

use App\Models\Contract;
use App\Repositories\User\Contract\ContractReadRepository;
use App\Services\Fiscal\AvailableYearsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le calcul des bornes d'années sur les contrats — méthode
 * fondatrice de la doctrine temporelle (chantier η Phase 0.1) consommée
 * par {@see AvailableYearsResolver}.
 *
 * Vérifie en particulier l'exclusion explicite des soft-deletes
 * (décision HD2 du chantier η — la requête attaque la table via
 * `DB::table()` qui n'applique pas le scope global SoftDeletes).
 */
final class ContractReadRepositoryYearBoundsTest extends TestCase
{
    use RefreshDatabase;

    private ContractReadRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ContractReadRepository;
    }

    #[Test]
    public function table_vide_retourne_min_et_max_null(): void
    {
        $bounds = $this->repo->yearBounds();

        self::assertSame(['min' => null, 'max' => null], $bounds);
    }

    #[Test]
    public function un_seul_contrat_retourne_la_meme_annee_pour_min_et_max(): void
    {
        Contract::factory()->create([
            'start_date' => '2024-06-15',
            'end_date' => '2024-07-10',
        ]);

        $bounds = $this->repo->yearBounds();

        self::assertSame(['min' => 2024, 'max' => 2024], $bounds);
    }

    #[Test]
    public function plusieurs_contrats_retournent_min_et_max_distincts(): void
    {
        Contract::factory()->create(['start_date' => '2023-03-10', 'end_date' => '2023-04-10']);
        Contract::factory()->create(['start_date' => '2025-09-01', 'end_date' => '2025-10-01']);

        $bounds = $this->repo->yearBounds();

        self::assertSame(['min' => 2023, 'max' => 2025], $bounds);
    }

    #[Test]
    public function les_contrats_soft_deletes_sont_exclus_du_calcul(): void
    {
        // Contrat soft-deleté en 2022 (donc en théorie min) — doit être ignoré.
        $deleted = Contract::factory()->create([
            'start_date' => '2022-01-15',
            'end_date' => '2022-02-15',
        ]);
        $deleted->delete();

        // Contrat actif 2024.
        Contract::factory()->create(['start_date' => '2024-05-01', 'end_date' => '2024-06-01']);

        $bounds = $this->repo->yearBounds();

        self::assertSame(['min' => 2024, 'max' => 2024], $bounds);
    }
}

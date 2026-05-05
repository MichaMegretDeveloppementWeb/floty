<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que toute mutation d'un {@see Contract} invalide bien le cache
 * porté par {@see AvailableYearsResolver} (chantier η Phase 0.2).
 *
 * Le pattern de chaque test :
 *   1. Lecture initiale du resolver → peuple le cache
 *   2. Mutation du Contract (create / update / delete / restore /
 *      forceDelete)
 *   3. Nouvelle lecture → doit refléter la mutation (si le cache n'était
 *      pas invalidé, on aurait l'ancienne valeur — test rouge)
 *
 * Année calendaire fixée à 2026 pour stabilité (cf.
 * {@see CarbonImmutable::setTestNow()}).
 */
final class ContractObserverTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_NOW_2026 = '2026-05-05 12:00:00';

    private AvailableYearsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::FAKE_NOW_2026);
        $this->resolver = $this->app->make(AvailableYearsResolver::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function la_creation_d_un_contrat_invalide_le_cache_immediatement(): void
    {
        // Lecture initiale (table vide) → cache peuplé sur [2026].
        self::assertSame([2026], $this->resolver->availableYears());

        Contract::factory()->create([
            'start_date' => '2023-06-15',
            'end_date' => '2023-07-10',
        ]);

        // Si l'observer marche, le cache a été invalidé : la nouvelle
        // lecture inclut 2023.
        self::assertSame([2023, 2024, 2025, 2026], $this->resolver->availableYears());
    }

    #[Test]
    public function la_modification_de_la_start_date_invalide_le_cache(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => '2024-06-15',
            'end_date' => '2024-07-10',
        ]);

        self::assertSame([2024, 2025, 2026], $this->resolver->availableYears());

        // On recule la start_date à 2022 — le cache doit refléter la
        // nouvelle borne min.
        $contract->update([
            'start_date' => '2022-01-15',
            'end_date' => '2022-02-15',
        ]);

        self::assertSame([2022, 2023, 2024, 2025, 2026], $this->resolver->availableYears());
    }

    #[Test]
    public function le_soft_delete_invalide_le_cache_et_retire_l_annee_si_orpheline(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => '2023-06-15',
            'end_date' => '2023-07-10',
        ]);

        self::assertSame([2023, 2024, 2025, 2026], $this->resolver->availableYears());

        $contract->delete();

        // 2023 n'a plus de contrat actif → disparaît du range.
        self::assertSame([2026], $this->resolver->availableYears());
    }

    #[Test]
    public function le_restore_d_un_soft_delete_remet_l_annee_dans_le_range(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => '2023-06-15',
            'end_date' => '2023-07-10',
        ]);
        $contract->delete();

        self::assertSame([2026], $this->resolver->availableYears());

        $contract->restore();

        self::assertSame([2023, 2024, 2025, 2026], $this->resolver->availableYears());
    }

    #[Test]
    public function le_force_delete_invalide_le_cache_et_supprime_definitivement_l_annee(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => '2023-06-15',
            'end_date' => '2023-07-10',
        ]);

        self::assertSame([2023, 2024, 2025, 2026], $this->resolver->availableYears());

        $contract->forceDelete();

        self::assertSame([2026], $this->resolver->availableYears());
    }
}

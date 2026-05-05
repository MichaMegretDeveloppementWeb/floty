<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Spécifie le comportement du {@see AvailableYearsResolver}, fondation
 * de la doctrine temporelle (chantier η Phase 0.1).
 *
 * **Choix d'isolation** : ces tests sont **purs** (pas de DB ni de
 * framework Laravel booté). Le repo est mocké via PHPUnit `createMock`
 * pour contrôler précisément les bornes retournées, et le cache utilise
 * un `ArrayStore` éphémère pour vérifier le comportement de mémorisation
 * sans toucher à la base. Cela garantit :
 *   - Stabilité (pas de fixture, pas de migration)
 *   - Vitesse (~ms)
 *   - Indépendance d'horloge : chaque test fixe `CarbonImmutable::setTestNow()`
 *
 * Le comportement SQL réel est couvert séparément par
 * `ContractReadRepositoryYearBoundsTest`.
 */
final class AvailableYearsResolverTest extends TestCase
{
    private const FAKE_NOW_2026 = '2026-05-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::FAKE_NOW_2026);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function aucun_contrat_fait_tomber_min_et_max_sur_l_annee_courante(): void
    {
        $resolver = $this->makeResolver(repoBounds: ['min' => null, 'max' => null]);

        self::assertSame(2026, $resolver->currentYear());
        self::assertSame(2026, $resolver->minYear());
        self::assertSame(2026, $resolver->maxYear());
        self::assertSame([2026], $resolver->availableYears());
    }

    #[Test]
    public function un_contrat_2024_etend_la_borne_min_jusqu_a_2024(): void
    {
        $resolver = $this->makeResolver(repoBounds: ['min' => 2024, 'max' => 2024]);

        self::assertSame(2024, $resolver->minYear());
        self::assertSame(2026, $resolver->maxYear());
        self::assertSame([2024, 2025, 2026], $resolver->availableYears());
    }

    #[Test]
    public function un_contrat_futur_2027_etend_la_borne_max_au_dela_de_l_annee_courante(): void
    {
        $resolver = $this->makeResolver(repoBounds: ['min' => 2024, 'max' => 2027]);

        self::assertSame(2024, $resolver->minYear());
        self::assertSame(2027, $resolver->maxYear());
        self::assertSame([2024, 2025, 2026, 2027], $resolver->availableYears());
    }

    #[Test]
    public function les_contrats_2023_et_2024_etalent_la_plage_jusqu_a_l_annee_courante(): void
    {
        $resolver = $this->makeResolver(repoBounds: ['min' => 2023, 'max' => 2024]);

        self::assertSame(2023, $resolver->minYear());
        self::assertSame(2026, $resolver->maxYear());
        self::assertSame([2023, 2024, 2025, 2026], $resolver->availableYears());
    }

    #[Test]
    public function le_resultat_du_repo_est_mis_en_cache_entre_appels_successifs(): void
    {
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        // Garantie principale : repo appelé EXACTEMENT 1 fois, peu importe
        // le nombre d'appels au resolver.
        $repo->expects(self::once())
            ->method('yearBounds')
            ->willReturn(['min' => 2024, 'max' => 2024]);

        $resolver = new AvailableYearsResolver($repo, $this->arrayCache());

        // 4 appels successifs — le cache absorbe tout après le 1er.
        self::assertSame(2024, $resolver->minYear());
        self::assertSame(2026, $resolver->maxYear());
        self::assertSame([2024, 2025, 2026], $resolver->availableYears());
        self::assertSame(2024, $resolver->minYear());
    }

    #[Test]
    public function forget_cache_force_une_nouvelle_lecture_du_repo(): void
    {
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        $repo->expects(self::exactly(2))
            ->method('yearBounds')
            ->willReturnOnConsecutiveCalls(
                ['min' => 2024, 'max' => 2024],
                ['min' => 2023, 'max' => 2024],
            );

        $resolver = new AvailableYearsResolver($repo, $this->arrayCache());

        self::assertSame(2024, $resolver->minYear());

        // Simule l'invalidation que portera le ContractObserver (chantier 0.2).
        $resolver->forgetCache();

        self::assertSame(2023, $resolver->minYear());
    }

    /**
     * @param  array{min: int|null, max: int|null}  $repoBounds
     */
    private function makeResolver(array $repoBounds): AvailableYearsResolver
    {
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        $repo->method('yearBounds')->willReturn($repoBounds);

        return new AvailableYearsResolver($repo, $this->arrayCache());
    }

    private function arrayCache(): CacheRepository
    {
        return new CacheRepository(new ArrayStore);
    }
}

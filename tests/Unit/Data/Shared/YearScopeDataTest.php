<?php

declare(strict_types=1);

namespace Tests\Unit\Data\Shared;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre construction directe, `fromResolver()`, `fromResolverAndRegistry()`,
 * et round-trip Spatie Data.
 */
final class YearScopeDataTest extends TestCase
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
    public function le_constructeur_assigne_directement_les_proprietes(): void
    {
        $dto = new YearScopeData(
            currentYear: 2026,
            minYear: 2024,
            availableYears: [2024, 2025, 2026],
        );

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2024, $dto->minYear);
        self::assertSame([2024, 2025, 2026], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_compose_le_dto_depuis_les_3_methodes_du_service(): void
    {
        // Resolver `final` instancié pour de vrai (PHPUnit refuse de
        // mocker une classe final) avec dépendances mockées.
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        $repo->method('yearBounds')->willReturn(['min' => 2023, 'max' => 2024]);
        $resolver = new AvailableYearsResolver($repo, new CacheRepository(new ArrayStore));

        $dto = YearScopeData::fromResolver($resolver);

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2023, $dto->minYear);
        self::assertSame([2023, 2024, 2025, 2026], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_and_registry_instance_vierge_borne_sur_les_annees_fiscales(): void
    {
        // Registry 2024-2026, aucun contrat, année courante 2026.
        $dto = YearScopeData::fromResolverAndRegistry(
            $this->makeResolver(null, null),
            $this->makeRegistry([2024, 2025, 2026]),
        );

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2024, $dto->minYear);
        self::assertSame([2024, 2025, 2026], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_and_registry_etend_vers_le_bas_pour_un_contrat_historique(): void
    {
        // Contrat en 2023 (sous le registre), année courante 2026.
        $dto = YearScopeData::fromResolverAndRegistry(
            $this->makeResolver(2023, 2023),
            $this->makeRegistry([2024, 2025, 2026]),
        );

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2023, $dto->minYear);
        self::assertSame([2023, 2024, 2025, 2026], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_and_registry_etend_vers_le_haut_pour_un_contrat_anticipe(): void
    {
        // Contrat anticipé en 2027 (au-dessus du registre), année courante 2026.
        $dto = YearScopeData::fromResolverAndRegistry(
            $this->makeResolver(2027, 2027),
            $this->makeRegistry([2024, 2025, 2026]),
        );

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2024, $dto->minYear);
        self::assertSame([2024, 2025, 2026, 2027], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_and_registry_inclut_l_annee_courante_hors_registre(): void
    {
        // On est en 2027 mais aucune règle Year2027 n'est codée; aucun contrat.
        CarbonImmutable::setTestNow('2027-03-01 12:00:00');

        $dto = YearScopeData::fromResolverAndRegistry(
            $this->makeResolver(null, null),
            $this->makeRegistry([2024, 2025, 2026]),
        );

        self::assertSame(2027, $dto->currentYear);
        self::assertSame(2024, $dto->minYear);
        self::assertSame([2024, 2025, 2026, 2027], $dto->availableYears);
    }

    #[Test]
    public function from_resolver_and_registry_unionne_contrats_passes_et_futurs_avec_le_registre(): void
    {
        // Contrats de 2023 à 2027, registre 2024-2026, année courante 2026.
        $dto = YearScopeData::fromResolverAndRegistry(
            $this->makeResolver(2023, 2027),
            $this->makeRegistry([2024, 2025, 2026]),
        );

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2023, $dto->minYear);
        self::assertSame([2023, 2024, 2025, 2026, 2027], $dto->availableYears);
    }

    #[Test]
    public function la_serialisation_to_array_produit_les_3_cles_camel_case(): void
    {
        $dto = new YearScopeData(
            currentYear: 2026,
            minYear: 2024,
            availableYears: [2024, 2025, 2026],
        );

        self::assertSame(
            [
                'currentYear' => 2026,
                'minYear' => 2024,
                'availableYears' => [2024, 2025, 2026],
            ],
            $dto->toArray(),
        );
    }

    #[Test]
    public function l_hydratation_from_round_trip_le_payload_serialise(): void
    {
        $original = new YearScopeData(
            currentYear: 2026,
            minYear: 2024,
            availableYears: [2024, 2025, 2026],
        );

        $rebuilt = YearScopeData::from($original->toArray());

        self::assertSame($original->currentYear, $rebuilt->currentYear);
        self::assertSame($original->minYear, $rebuilt->minYear);
        self::assertSame($original->availableYears, $rebuilt->availableYears);
    }

    /**
     * Builds a real `AvailableYearsResolver` (final, unmockable) backed by a
     * mocked contract repository returning the given `yearBounds`.
     */
    private function makeResolver(?int $min, ?int $max): AvailableYearsResolver
    {
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        $repo->method('yearBounds')->willReturn(['min' => $min, 'max' => $max]);

        return new AvailableYearsResolver($repo, new CacheRepository(new ArrayStore));
    }

    /**
     * Builds a fresh registry (not the booted singleton) holding exactly the
     * given years. Rule lists are irrelevant here · only `registeredYears()`
     * is consulted by the union builder.
     *
     * @param  list<int>  $years
     */
    private function makeRegistry(array $years): FiscalRuleRegistry
    {
        $registry = new FiscalRuleRegistry($this->app);
        foreach ($years as $year) {
            $registry->register($year, []);
        }

        return $registry;
    }
}

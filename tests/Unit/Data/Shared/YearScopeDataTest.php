<?php

declare(strict_types=1);

namespace Tests\Unit\Data\Shared;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le DTO {@see YearScopeData} : construction directe, factory
 * `fromResolver()`, et round-trip Spatie Data (sérialisation +
 * hydratation).
 *
 * Tests purs (pas de framework Laravel booté ni de DB). Le test de
 * `fromResolver()` injecte un vrai `AvailableYearsResolver` (classe
 * `final`) avec ses dépendances mockées — pattern aligné sur
 * `AvailableYearsResolverTest`.
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
        // Resolver `final` instancié pour de vrai avec dépendances mockées
        // (PHPUnit refuse de mocker une classe final ; cf. pattern aligné
        // sur AvailableYearsResolverTest). On contrôle les bornes via le
        // repo mocké.
        $repo = $this->createMock(ContractReadRepositoryInterface::class);
        $repo->method('yearBounds')->willReturn(['min' => 2023, 'max' => 2024]);
        $resolver = new AvailableYearsResolver($repo, new CacheRepository(new ArrayStore));

        $dto = YearScopeData::fromResolver($resolver);

        self::assertSame(2026, $dto->currentYear);
        self::assertSame(2023, $dto->minYear);
        self::assertSame([2023, 2024, 2025, 2026], $dto->availableYears);
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
}

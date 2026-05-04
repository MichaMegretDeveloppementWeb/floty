<?php

declare(strict_types=1);

namespace Tests\Unit\Data\Shared\Listing;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit pour {@see IndexQueryData} — validation des paramètres
 * partagés (page, perPage, search, sortKey, sortDirection) et de la
 * whitelist `sortKey` via late static binding sur les sous-classes.
 *
 * Utilise une fixture {@see FixtureIndexQuery} avec une whitelist
 * sortKey contrôlée pour valider le comportement abstrait.
 */
final class IndexQueryDataValidationTest extends TestCase
{
    #[Test]
    public function defaults_are_applied_when_no_params(): void
    {
        $query = FixtureIndexQuery::from([]);

        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->perPage);
        $this->assertNull($query->search);
        $this->assertNull($query->sortKey);
        $this->assertSame(SortDirection::Asc, $query->sortDirection);
    }

    #[Test]
    public function per_page_must_be_in_whitelist(): void
    {
        $this->expectException(ValidationException::class);

        FixtureIndexQuery::validate(['perPage' => 33]);
    }

    #[Test]
    public function per_page_accepts_each_whitelisted_value(): void
    {
        foreach ([10, 20, 50, 100] as $valid) {
            $query = FixtureIndexQuery::from(['perPage' => $valid]);
            $this->assertSame($valid, $query->perPage);
        }
    }

    #[Test]
    public function page_must_be_at_least_one(): void
    {
        $this->expectException(ValidationException::class);

        FixtureIndexQuery::validate(['page' => 0]);
    }

    #[Test]
    public function sort_key_must_be_in_subclass_whitelist(): void
    {
        $this->expectException(ValidationException::class);

        FixtureIndexQuery::validate(['sortKey' => 'forbidden_column']);
    }

    #[Test]
    public function sort_key_accepts_whitelisted_value(): void
    {
        $query = FixtureIndexQuery::from(['sortKey' => 'name']);
        $this->assertSame('name', $query->sortKey);
    }

    #[Test]
    public function sort_direction_must_be_asc_or_desc(): void
    {
        $this->expectException(ValidationException::class);

        FixtureIndexQuery::validate(['sortDirection' => 'sideways']);
    }

    #[Test]
    public function sort_direction_accepts_desc(): void
    {
        $query = FixtureIndexQuery::from(['sortDirection' => 'desc']);
        $this->assertSame(SortDirection::Desc, $query->sortDirection);
    }

    #[Test]
    public function search_empty_string_is_normalized_to_null(): void
    {
        $query = FixtureIndexQuery::from(['search' => '']);
        $this->assertNull($query->search);
    }

    #[Test]
    public function search_accepts_string(): void
    {
        $query = FixtureIndexQuery::from(['search' => 'foo']);
        $this->assertSame('foo', $query->search);
    }

    #[Test]
    public function search_max_length_enforced(): void
    {
        $this->expectException(ValidationException::class);

        FixtureIndexQuery::validate(['search' => str_repeat('a', 256)]);
    }
}

/**
 * Fixture concrète pour tester {@see IndexQueryData}. Whitelist
 * sortKey volontairement réduite à 2 valeurs pour valider la logique
 * de late static binding.
 */
final class FixtureIndexQuery extends IndexQueryData
{
    public static function allowedSortKeys(): array
    {
        return ['name', 'createdAt'];
    }
}

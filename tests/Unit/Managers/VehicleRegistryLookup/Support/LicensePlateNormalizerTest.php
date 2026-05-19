<?php

declare(strict_types=1);

namespace Tests\Unit\Managers\VehicleRegistryLookup\Support;

use App\Managers\VehicleRegistryLookup\Support\LicensePlateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LicensePlateNormalizerTest extends TestCase
{
    #[Test]
    #[DataProvider('plateVariants')]
    public function normalise_les_plaques_en_uppercase_sans_separateurs(string $input, string $expected): void
    {
        $this->assertSame($expected, LicensePlateNormalizer::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plateVariants(): iterable
    {
        yield 'format SIV standard avec tirets' => ['AB-123-CD', 'AB123CD'];
        yield 'format SIV avec espaces' => ['AB 123 CD', 'AB123CD'];
        yield 'lowercase' => ['ab-123-cd', 'AB123CD'];
        yield 'mixed case sans separateurs' => ['Ab123Cd', 'AB123CD'];
        yield 'separateurs multiples' => ['AB--123  CD', 'AB123CD'];
        yield 'point comme separateur' => ['AB.123.CD', 'AB123CD'];
        yield 'deja normalisee' => ['AB123CD', 'AB123CD'];
    }
}

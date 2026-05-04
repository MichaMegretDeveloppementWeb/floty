<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Company;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unit du helper Company::generateShortCode() — algo validé chantier A.
 */
final class CompanyShortCodeGenerationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideCases(): iterable
    {
        yield 'trois mots simples' => ['Distribution Régionale Sud', 'DRS'];
        yield 'quatre mots' => ['Société Anonyme De Transport', 'SAD'];
        yield 'deux mots' => ['BTP Confort', 'BCO'];
        yield 'deux mots accents' => ['Café Hôtelier', 'CHO'];
        yield 'un mot quatre lettres' => ['ACME', 'ACM'];
        yield 'un mot court trois lettres' => ['SAS', 'SAS'];
        yield 'un mot très court avec padding' => ['ON', 'ONX'];
        yield 'un mot une lettre avec padding' => ['X', 'XXX'];
        yield 'casse mixte' => ['acme logistique france', 'ALF'];
        yield 'espaces multiples deux mots' => ['  ACME   Logistique  ', 'ALO'];
        yield 'caracteres speciaux ignores' => ['Café & Hôtel', 'CHO'];
        yield 'string vide' => ['', 'XXX'];
        yield 'que des chiffres' => ['12345', 'XXX'];
    }

    #[Test]
    #[DataProvider('provideCases')]
    public function generate_short_code_produit_le_bon_resultat(string $legalName, string $expected): void
    {
        $this->assertSame($expected, Company::generateShortCode($legalName));
    }
}

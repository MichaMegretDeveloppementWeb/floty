<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\BaseAppException;
use App\Exceptions\Fiscal\FiscalCalculationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les factory methods de `FiscalCalculationException`
 * produisent les bons messages techniques (anglais) et utilisateurs
 * (français), conformément à `gestion-erreurs.md`.
 */
final class FiscalCalculationExceptionTest extends TestCase
{
    #[Test]
    public function year_not_supported_porte_les_deux_messages(): void
    {
        $e = FiscalCalculationException::yearNotSupported(2099);

        self::assertInstanceOf(BaseAppException::class, $e);
        self::assertStringContainsString('2099', $e->getMessage());
        self::assertStringContainsString('not supported', $e->getMessage());
        self::assertStringContainsString('2099', $e->getUserMessage());
        self::assertStringContainsString("n'est pas supportée", $e->getUserMessage());
    }

    #[Test]
    public function negative_days_porte_la_valeur_dans_le_message_technique(): void
    {
        $e = FiscalCalculationException::negativeDays(-5);

        self::assertStringContainsString('-5', $e->getMessage());
        self::assertStringContainsString('invalide', $e->getUserMessage());
    }

    #[Test]
    public function cumul_inferior_to_assigned_porte_les_deux_valeurs(): void
    {
        $e = FiscalCalculationException::cumulInferiorToAssigned(50, 100);

        self::assertStringContainsString('50', $e->getMessage());
        self::assertStringContainsString('100', $e->getMessage());
        self::assertStringContainsString('cumul', $e->getUserMessage());
    }

    #[Test]
    public function missing_fiscal_characteristics_porte_le_vehicle_id(): void
    {
        $e = FiscalCalculationException::missingFiscalCharacteristics(42);

        self::assertStringContainsString('42', $e->getMessage());
        self::assertStringContainsString('fiscales actives', $e->getUserMessage());
    }
}

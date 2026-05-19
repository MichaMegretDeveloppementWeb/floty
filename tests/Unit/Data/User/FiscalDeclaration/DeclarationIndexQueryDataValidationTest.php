<?php

declare(strict_types=1);

namespace Tests\Unit\Data\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fou : la validation du filtre `status` suit l'enum
 * FiscalDeclarationStatus via Rule::enum (pas de liste codée en dur).
 */
final class DeclarationIndexQueryDataValidationTest extends TestCase
{
    #[Test]
    public function status_accepts_chaque_case_de_l_enum(): void
    {
        foreach (FiscalDeclarationStatus::cases() as $status) {
            $query = DeclarationIndexQueryData::from(['status' => $status->value]);
            self::assertSame($status, $query->status);
        }
    }

    #[Test]
    public function status_rejette_une_valeur_hors_enum(): void
    {
        $this->expectException(ValidationException::class);

        DeclarationIndexQueryData::validate(['status' => 'archived']);
    }

    #[Test]
    public function status_null_est_accepte(): void
    {
        $query = DeclarationIndexQueryData::from(['status' => null]);
        self::assertNull($query->status);
    }
}

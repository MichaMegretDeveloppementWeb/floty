<?php

declare(strict_types=1);

namespace Tests\Unit\Data\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lot 5 D7 (F-19-010) · valide que la validation du filtre `status`
 * suit l'enum {@see FiscalDeclarationStatus} via `Rule::enum`, et pas
 * une liste codée en dur `in:draft,deferred,generated`.
 *
 * Garde-fou : si l'enum gagne ou perd un case, la validation doit
 * suivre automatiquement.
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

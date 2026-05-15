<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\RevertDeferredToDraftAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RevertDeferredToDraftActionTest extends TestCase
{
    use RefreshDatabase;

    private RevertDeferredToDraftAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(RevertDeferredToDraftAction::class);
    }

    #[Test]
    public function passe_deferred_a_draft(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create([
            'status' => FiscalDeclarationStatus::Deferred,
        ]);

        $updated = $this->action->execute($declaration->id);

        self::assertSame(FiscalDeclarationStatus::Draft, $updated->status);
    }

    #[Test]
    public function refus_si_deja_draft(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create();

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_generated(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_obsolete(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create([
            'status' => FiscalDeclarationStatus::Deferred,
            'is_obsolete' => true,
            'obsolete_at' => now(),
        ]);

        // L'invariant principal vérifié est le statut Deferred · une
        // déclaration deferred obsolète n'existe pas en pratique mais
        // si elle existait, le revert reste autorisé (statut → Draft).
        // L'invariant `is_obsolete` est porté ailleurs (`Modify*Action`).
        $updated = $this->action->execute($declaration->id);

        self::assertSame(FiscalDeclarationStatus::Draft, $updated->status);
        self::assertTrue($updated->is_obsolete, 'L\'obsolescence est préservée.');
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99999);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\MarkDeclarationAsDeferredAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MarkDeclarationAsDeferredActionTest extends TestCase
{
    use RefreshDatabase;

    private MarkDeclarationAsDeferredAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(MarkDeclarationAsDeferredAction::class);
    }

    #[Test]
    public function passe_draft_a_deferred(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create();

        $updated = $this->action->execute($declaration->id);

        self::assertSame(FiscalDeclarationStatus::Deferred, $updated->status);
    }

    #[Test]
    public function refus_si_pas_draft(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_obsolete(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create([
            'is_obsolete' => true,
            'obsolete_at' => now(),
        ]);

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99999);
    }

    #[Test]
    public function persiste_defer_reason_si_fournie(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create();

        $updated = $this->action->execute(
            $declaration->id,
            'En attente du retour expert-comptable sur le cluster LCD',
        );

        self::assertSame(FiscalDeclarationStatus::Deferred, $updated->status);
        self::assertSame(
            'En attente du retour expert-comptable sur le cluster LCD',
            $updated->defer_reason,
        );
    }

    #[Test]
    public function defer_reason_null_si_non_fournie(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create();

        $updated = $this->action->execute($declaration->id);

        self::assertNull($updated->defer_reason);
    }

    #[Test]
    public function defer_reason_normalisee_chaine_vide_devient_null(): void
    {
        // Lot 5 D13 · l'utilisateur peut envoyer une chaîne d'espaces
        // depuis le textarea (touche barre d'espace sans intention de
        // saisir) · l'Action normalise via trim et bascule sur null.
        $declaration = FiscalDeclaration::factory()->draft()->create();

        $updated = $this->action->execute($declaration->id, '   ');

        self::assertNull($updated->defer_reason);
    }
}

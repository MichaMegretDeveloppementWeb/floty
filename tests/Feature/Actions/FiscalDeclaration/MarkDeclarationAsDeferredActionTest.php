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
}

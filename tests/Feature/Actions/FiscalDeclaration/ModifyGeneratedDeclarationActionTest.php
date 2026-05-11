<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\ModifyGeneratedDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModifyGeneratedDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private ModifyGeneratedDeclarationAction $action;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(ModifyGeneratedDeclarationAction::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function transitionne_s5_vers_s7_en_creant_un_brouillon_chaine(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $newDraft = $this->action->execute($generated->id, $this->user->id, $this->user->full_name);

        self::assertNotSame($generated->id, $newDraft->id);
        self::assertSame(FiscalDeclarationStatus::Draft, $newDraft->status);
        self::assertFalse($newDraft->is_obsolete);

        $previous = $generated->fresh();
        self::assertTrue($previous->is_obsolete);
        self::assertNotNull($previous->obsolete_at);
        self::assertSame($newDraft->id, $previous->superseded_by_id);
    }

    #[Test]
    public function empile_un_motif_voluntary_modification_avec_acteur(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->action->execute($generated->id, $this->user->id, $this->user->full_name);

        $previous = $generated->fresh();
        self::assertIsArray($previous->obsolete_reasons);
        self::assertCount(1, $previous->obsolete_reasons);
        self::assertSame(
            InvalidationReasonType::VoluntaryModification->value,
            $previous->obsolete_reasons[0]['type'],
        );
        self::assertSame($this->user->id, $previous->obsolete_reasons[0]['actor_user_id']);
        self::assertSame('user', $previous->obsolete_reasons[0]['entity']['type']);
        self::assertSame($this->user->full_name, $previous->obsolete_reasons[0]['entity']['label']);
    }

    #[Test]
    public function refus_si_declaration_deja_obsolete(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/modification volontaire/');

        $this->action->execute($obsolete->id, $this->user->id, $this->user->full_name);
    }

    #[Test]
    public function refus_si_brouillon(): void
    {
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->expectException(DomainException::class);

        $this->action->execute($draft->id, $this->user->id, $this->user->full_name);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99_999, $this->user->id, $this->user->full_name);
    }
}

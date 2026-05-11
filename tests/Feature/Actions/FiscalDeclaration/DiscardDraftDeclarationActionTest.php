<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\DiscardDraftDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscardDraftDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private DiscardDraftDeclarationAction $action;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DiscardDraftDeclarationAction::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function soft_delete_le_brouillon_sans_predecessor(): void
    {
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->action->execute($draft->id);

        self::assertNotNull($draft->fresh()->deleted_at);
        self::assertNull(FiscalDeclaration::query()->find($draft->id));
    }

    #[Test]
    public function reactivate_le_predecessor_si_obsolescence_volontaire_seulement(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create([
                'is_obsolete' => true,
                'obsolete_at' => now(),
                'obsolete_reasons' => [
                    [
                        'type' => InvalidationReasonType::VoluntaryModification->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'user', 'id' => 1, 'label' => 'Admin'],
                        'fields_changed' => [],
                    ],
                ],
            ]);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $reactivated = $predecessor->fresh();
        self::assertFalse($reactivated->is_obsolete);
        self::assertNull($reactivated->obsolete_at);
        self::assertNull($reactivated->obsolete_reasons);
        self::assertNull($reactivated->superseded_by_id);
        self::assertSame(FiscalDeclarationStatus::Generated, $reactivated->status);
    }

    #[Test]
    public function garde_predecessor_obsolete_si_motifs_mixtes(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create([
                'obsolete_reasons' => [
                    [
                        'type' => InvalidationReasonType::ContractCreated->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'contract', 'id' => 42, 'label' => 'EA-001'],
                        'fields_changed' => [],
                    ],
                    [
                        'type' => InvalidationReasonType::VoluntaryModification->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'user', 'id' => 1, 'label' => 'Admin'],
                        'fields_changed' => [],
                    ],
                ],
            ]);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $fresh = $predecessor->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertNotNull($fresh->obsolete_at);
        self::assertCount(2, $fresh->obsolete_reasons);
        self::assertNull($fresh->superseded_by_id);
    }

    #[Test]
    public function garde_predecessor_obsolete_si_motifs_perimetre_uniquement(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $fresh = $predecessor->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertNull($fresh->superseded_by_id);
    }

    #[Test]
    public function refus_si_declaration_generated(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/brouillon/');

        $this->action->execute($generated->id);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99_999);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\MarkDeclarationAsObsoleteAction;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\FiscalDeclaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MarkDeclarationAsObsoleteActionTest extends TestCase
{
    use RefreshDatabase;

    private MarkDeclarationAsObsoleteAction $action;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(MarkDeclarationAsObsoleteAction::class);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function pose_is_obsolete_et_obsolete_at_au_premier_appel(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        $this->action->execute($declaration->id, $this->makeReason());

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertNotNull($fresh->obsolete_at);
        self::assertCount(1, $fresh->obsolete_reasons);
        self::assertSame(
            InvalidationReasonType::ContractUpdated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function empile_les_motifs_sur_appels_successifs(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        $this->action->execute($declaration->id, $this->makeReason(InvalidationReasonType::ContractUpdated));
        $this->action->execute($declaration->id, $this->makeReason(InvalidationReasonType::VfcUpdated));
        $this->action->execute($declaration->id, $this->makeReason(InvalidationReasonType::ContractDeleted));

        $fresh = $declaration->fresh();
        self::assertCount(3, $fresh->obsolete_reasons);
        self::assertSame(InvalidationReasonType::ContractUpdated->value, $fresh->obsolete_reasons[0]['type']);
        self::assertSame(InvalidationReasonType::VfcUpdated->value, $fresh->obsolete_reasons[1]['type']);
        self::assertSame(InvalidationReasonType::ContractDeleted->value, $fresh->obsolete_reasons[2]['type']);
    }

    #[Test]
    public function ne_modifie_pas_obsolete_at_apres_le_premier_appel(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        Carbon::setTestNow('2026-01-15 10:00:00');
        $this->action->execute($declaration->id, $this->makeReason());
        $firstFlagAt = $declaration->fresh()->obsolete_at;

        Carbon::setTestNow('2026-02-20 14:00:00');
        $this->action->execute($declaration->id, $this->makeReason(InvalidationReasonType::VfcUpdated));

        self::assertEquals($firstFlagAt->toDateTimeString(), $declaration->fresh()->obsolete_at->toDateTimeString());
        Carbon::setTestNow();
    }

    #[Test]
    public function le_statut_reste_inchange_apres_marquage(): void
    {
        $declaration = FiscalDeclaration::factory()->generated()->create();

        $this->action->execute($declaration->id, $this->makeReason());

        // Le statut reste `generated` même après obsolescence (cf. ADR § D4 rev. 1.1).
        self::assertSame('generated', $declaration->fresh()->status->value);
    }

    private function makeReason(?InvalidationReasonType $type = null): InvalidationReasonData
    {
        return new InvalidationReasonData(
            type: $type ?? InvalidationReasonType::ContractUpdated,
            occurredAt: Carbon::now()->toIso8601String(),
            actorUserId: $this->user->id,
            entity: ['type' => 'contract', 'id' => 42, 'label' => 'Test motif'],
            fieldsChanged: ['start_date'],
        );
    }
}

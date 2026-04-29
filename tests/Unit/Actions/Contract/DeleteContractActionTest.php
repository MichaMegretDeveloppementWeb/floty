<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\DeleteContractAction;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de la suppression (soft delete) d'un contrat. Un contrat
 * supprimé n'apparaît plus dans la détection d'overlap (le trigger DB
 * filtre `deleted_at IS NULL`), donc sa plage est immédiatement
 * réutilisable pour un nouveau contrat.
 */
final class DeleteContractActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteContractAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DeleteContractAction::class);
    }

    #[Test]
    public function effectue_un_soft_delete_du_contrat(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $this->action->execute($contract->id);

        $this->assertSoftDeleted($contract);
        $this->assertNull(Contract::query()->find($contract->id));
        $this->assertNotNull(Contract::withTrashed()->find($contract->id));
    }
}

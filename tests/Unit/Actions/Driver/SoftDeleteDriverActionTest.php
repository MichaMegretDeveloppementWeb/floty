<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Driver;

use App\Actions\Driver\SoftDeleteDriverAction;
use App\Exceptions\Driver\DriverDeletionBlockedException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SoftDeleteDriverActionTest extends TestCase
{
    use RefreshDatabase;

    private SoftDeleteDriverAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(SoftDeleteDriverAction::class);
    }

    #[Test]
    public function soft_delete_le_driver_quand_aucun_contrat_associe(): void
    {
        $driver = Driver::factory()->create();

        $this->action->execute($driver);

        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    }

    #[Test]
    public function refuse_la_suppression_si_le_driver_a_des_contrats(): void
    {
        $driver = Driver::factory()->create();
        Contract::factory()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'company_id' => Company::factory()->create()->id,
            'driver_id' => $driver->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        $this->expectException(DriverDeletionBlockedException::class);

        $this->action->execute($driver);
    }
}

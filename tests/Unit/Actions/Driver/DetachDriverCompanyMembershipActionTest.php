<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Driver;

use App\Actions\Driver\DetachDriverCompanyMembershipAction;
use App\Exceptions\Driver\DriverCompanyMembershipBlockedException;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DetachDriverCompanyMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private DetachDriverCompanyMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DetachDriverCompanyMembershipAction::class);
    }

    #[Test]
    public function supprime_la_membership_quand_aucun_contrat_associe(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute($pivotId);

        $this->assertDatabaseMissing('driver_company', ['id' => $pivotId]);
    }

    #[Test]
    public function leve_driver_membership_not_found_exception_quand_le_pivot_id_est_introuvable(): void
    {
        $this->expectException(DriverMembershipNotFoundException::class);

        $this->action->execute(99999);
    }

    #[Test]
    public function refuse_la_suppression_si_la_membership_a_des_contrats_associes(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        Contract::factory()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        $this->expectException(DriverCompanyMembershipBlockedException::class);

        $this->action->execute($pivotId);
    }
}

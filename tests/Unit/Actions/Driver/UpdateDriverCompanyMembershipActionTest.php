<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Driver;

use App\Actions\Driver\UpdateDriverCompanyMembershipAction;
use App\Data\User\Driver\UpdateDriverCompanyMembershipData;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\MembershipChronologyException;
use App\Models\Company;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpdateDriverCompanyMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateDriverCompanyMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(UpdateDriverCompanyMembershipAction::class);
    }

    #[Test]
    public function met_a_jour_joined_at_pour_une_membership_active(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-06-01', 'left_at' => null]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(joinedAt: '2024-01-15'),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-01-15',
            'left_at' => null,
        ]);
    }

    #[Test]
    public function met_a_jour_joined_at_en_preservant_left_at_explicite(): void
    {
        // Pour préserver `left_at`, le payload doit le passer explicitement
        // (sinon `leftAt: null` par défaut déclenche la réactivation).
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2025-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(
                joinedAt: '2024-01-01',
                leftAt: '2025-12-31',
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-01-01',
            'left_at' => '2025-12-31',
        ]);
    }

    #[Test]
    public function autorise_joined_at_egal_a_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2024-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(
                joinedAt: '2024-12-31',
                leftAt: '2024-12-31',
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-12-31',
            'left_at' => '2024-12-31',
        ]);
    }

    #[Test]
    public function leve_membership_not_found_si_pivot_id_inconnu(): void
    {
        $this->expectException(DriverMembershipNotFoundException::class);

        $this->action->execute(
            99999,
            new UpdateDriverCompanyMembershipData(joinedAt: '2024-01-01'),
        );
    }

    #[Test]
    public function reactive_une_membership_sortie_quand_left_at_est_null(): void
    {
        // `leftAt: null` réactive la membership (efface `left_at`).
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2024-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(
                joinedAt: '2024-06-01',
                leftAt: null,
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-06-01',
            'left_at' => null,
        ]);
    }

    #[Test]
    public function met_a_jour_simultanement_joined_at_et_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2024-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(
                joinedAt: '2024-01-15',
                leftAt: '2025-06-30',
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-01-15',
            'left_at' => '2025-06-30',
        ]);
    }

    #[Test]
    public function leve_chronology_si_joined_at_est_apres_le_nouveau_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => null,
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->expectException(MembershipChronologyException::class);

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(
                joinedAt: '2025-01-01',
                leftAt: '2024-12-31',
            ),
        );
    }
}

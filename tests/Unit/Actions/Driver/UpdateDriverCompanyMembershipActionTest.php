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

/**
 * Couvre l'édition `joined_at` d'une membership Driver↔Company existante
 * (chantier B). Vérifie le happy path + les deux refus métier (pivot
 * introuvable et chronologie violée).
 */
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
    public function met_a_jour_joined_at_pour_une_membership_sortie(): void
    {
        // L'édition reste autorisée sur une membership avec left_at posé,
        // tant que la chronologie reste cohérente.
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2025-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(joinedAt: '2024-01-01'),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-01-01',
            'left_at' => '2025-12-31',
        ]);
    }

    #[Test]
    public function leve_chronology_exception_si_joined_at_est_apres_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2024-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->expectException(MembershipChronologyException::class);

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(joinedAt: '2025-01-01'),
        );
    }

    #[Test]
    public function autorise_joined_at_egal_a_left_at(): void
    {
        // Borne inclusive : `<=`, cohérent avec les autres validations
        // chronologiques du domaine.
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, [
            'joined_at' => '2024-06-01',
            'left_at' => '2024-12-31',
        ]);
        $pivotId = (int) DB::table('driver_company')->where('driver_id', $driver->id)->value('id');

        $this->action->execute(
            $pivotId,
            new UpdateDriverCompanyMembershipData(joinedAt: '2024-12-31'),
        );

        $this->assertDatabaseHas('driver_company', [
            'id' => $pivotId,
            'joined_at' => '2024-12-31',
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
}

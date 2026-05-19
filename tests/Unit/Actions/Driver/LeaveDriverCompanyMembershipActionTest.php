<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Driver;

use App\Actions\Driver\LeaveDriverCompanyMembershipAction;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Enums\Driver\FutureContractsResolutionMode;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LeaveDriverCompanyMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private LeaveDriverCompanyMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(LeaveDriverCompanyMembershipAction::class);
    }

    #[Test]
    public function mode_none_sans_contrats_a_venir_pose_simplement_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::None,
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'left_at' => '2026-06-30',
        ]);
    }

    #[Test]
    public function mode_none_ignore_les_contrats_a_venir_existants(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $futureContract = Contract::factory()->withDrivers([$driver])->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
        ]);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::None,
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'left_at' => '2026-06-30',
        ]);
        $this->assertDatabaseHas('contract_drivers', [
            'contract_id' => $futureContract->id,
            'driver_id' => $driver->id,
        ]);
    }

    #[Test]
    public function mode_detach_retire_le_sortant_de_tous_les_contrats_a_venir(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$driver])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);
        $c2 = Contract::factory()->withDrivers([$driver])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
        ]);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Detach,
            ),
        );

        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $c1->id, 'driver_id' => $driver->id,
        ]);
        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $c2->id, 'driver_id' => $driver->id,
        ]);
    }

    #[Test]
    public function mode_detach_preserve_les_autres_conducteurs_du_contrat(): void
    {
        $driver = Driver::factory()->create();
        $autre = Driver::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);
        $autre->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$driver, $autre])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Detach,
            ),
        );

        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $c1->id, 'driver_id' => $driver->id,
        ]);
        $this->assertDatabaseHas('contract_drivers', [
            'contract_id' => $c1->id, 'driver_id' => $autre->id,
        ]);
    }

    #[Test]
    public function mode_replace_avec_remplacements_valides_substitue_chaque_contrat(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $remplacant = Driver::factory()->create();
        $remplacant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [$c1->id => $remplacant->id],
            ),
        );

        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $c1->id, 'driver_id' => $sortant->id,
        ]);
        $this->assertDatabaseHas('contract_drivers', [
            'contract_id' => $c1->id, 'driver_id' => $remplacant->id,
        ]);
    }

    #[Test]
    public function mode_replace_avec_cle_manquante_leve_leave_resolution_invalid_exception(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->expectException(LeaveResolutionInvalidException::class);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [],
            ),
        );
    }

    #[Test]
    public function mode_replace_avec_driver_inexistant_leve_leave_resolution_invalid_exception(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->expectException(LeaveResolutionInvalidException::class);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [$c1->id => 99999],
            ),
        );
    }

    #[Test]
    public function mode_replace_avec_driver_pas_actif_sur_la_periode_leve_leave_resolution_invalid_exception(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $remplacant = Driver::factory()->create();
        // Remplaçant sort avant le contrat à remplacer.
        $remplacant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => '2026-12-31']);

        $c1 = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->expectException(LeaveResolutionInvalidException::class);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [$c1->id => $remplacant->id],
            ),
        );
    }

    #[Test]
    public function mode_replace_avec_valeur_null_retire_le_sortant_sans_remplacant(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $remplacant = Driver::factory()->create();
        $remplacant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $cDetache = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);
        $cReplace = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
        ]);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [
                    $cDetache->id => null,
                    $cReplace->id => $remplacant->id,
                ],
            ),
        );

        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $cDetache->id, 'driver_id' => $sortant->id,
        ]);
        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $cDetache->id, 'driver_id' => $remplacant->id,
        ]);

        $this->assertDatabaseMissing('contract_drivers', [
            'contract_id' => $cReplace->id, 'driver_id' => $sortant->id,
        ]);
        $this->assertDatabaseHas('contract_drivers', [
            'contract_id' => $cReplace->id, 'driver_id' => $remplacant->id,
        ]);
    }

    #[Test]
    public function mode_replace_sans_contrats_a_venir_pose_simplement_left_at(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();
        $driver->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [],
            ),
        );

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'left_at' => '2026-06-30',
        ]);
    }

    #[Test]
    public function leve_driver_membership_not_found_exception_quand_aucune_membership_active(): void
    {
        $driver = Driver::factory()->create();
        $company = Company::factory()->create();

        $this->expectException(DriverMembershipNotFoundException::class);

        $this->action->execute(
            $driver,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::None,
            ),
        );
    }

    #[Test]
    public function mode_replace_refuse_le_driver_sortant_comme_remplacant_de_lui_meme(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->expectException(LeaveResolutionInvalidException::class);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [$c1->id => $sortant->id],
            ),
        );
    }

    #[Test]
    public function mode_replace_refuse_un_remplacant_deja_attache_au_contrat(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $autre = Driver::factory()->create();
        $autre->companies()->attach($company->id, ['joined_at' => '2024-01-01', 'left_at' => null]);

        $c1 = Contract::factory()->withDrivers([$sortant, $autre])->create([
            'vehicle_id' => $vehicle->id, 'company_id' => $company->id,
            'start_date' => '2027-01-01', 'end_date' => '2027-01-31',
        ]);

        $this->expectException(LeaveResolutionInvalidException::class);

        $this->action->execute(
            $sortant,
            $company->id,
            new LeaveDriverCompanyMembershipData(
                leftAt: '2026-06-30',
                futureContractsResolution: FutureContractsResolutionMode::Replace,
                replacementMap: [$c1->id => $autre->id],
            ),
        );
    }
}

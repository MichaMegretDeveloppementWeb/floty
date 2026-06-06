<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VehicleEvent;

use App\Data\User\Vehicle\ExitVehicleData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Services\VehicleEvent\VehicleLifecycleEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du service qui matérialise les repères « cycle de vie » dans la
 * timeline des événements (acquisition + sortie de flotte). Ces repères sont
 * mono-jour, catégorie « Cycle de vie », non fiscaux, sans indisponibilité,
 * et marqués `system_kind` (lecture seule côté policy + front).
 */
final class VehicleLifecycleEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    private VehicleLifecycleEventRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = $this->app->make(VehicleLifecycleEventRecorder::class);
    }

    #[Test]
    public function record_acquisition_cree_un_repere_entree_en_flotte_mono_jour(): void
    {
        $vehicle = Vehicle::factory()->create(['acquisition_date' => '2024-03-08']);

        $this->recorder->recordAcquisition($vehicle);

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::Acquisition)
            ->sole();

        self::assertSame(VehicleEventType::Other, $event->type);
        self::assertSame('Entrée en flotte', $event->title);
        self::assertFalse($event->has_fiscal_impact);
        self::assertFalse($event->implies_unavailability);
        self::assertSame('2024-03-08', $event->start_date->toDateString());
        self::assertSame('2024-03-08', $event->end_date->toDateString());
        self::assertNull($event->description);
        self::assertSame(['Cycle de vie'], $event->categories()->pluck('category')->all());
    }

    #[Test]
    public function record_acquisition_est_idempotent_et_resynchronise_la_date(): void
    {
        $vehicle = Vehicle::factory()->create(['acquisition_date' => '2024-03-08']);

        $this->recorder->recordAcquisition($vehicle);

        // L'utilisateur corrige la date d'acquisition : on rejoue le repère.
        $vehicle->acquisition_date = Carbon::parse('2024-05-20');
        $this->recorder->recordAcquisition($vehicle);

        $events = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::Acquisition)
            ->get();

        self::assertCount(1, $events);
        self::assertSame('2024-05-20', $events->first()->start_date->toDateString());
    }

    #[Test]
    public function record_exit_cree_un_repere_sortie_de_flotte_avec_le_motif_en_description(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-06-15',
            exitReason: VehicleExitReason::Sold,
            note: null,
        ));

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::FleetExit)
            ->sole();

        self::assertSame(VehicleEventType::Other, $event->type);
        self::assertSame('Sortie de flotte', $event->title);
        self::assertFalse($event->has_fiscal_impact);
        self::assertFalse($event->implies_unavailability);
        self::assertSame('2025-06-15', $event->start_date->toDateString());
        self::assertSame('2025-06-15', $event->end_date->toDateString());
        self::assertSame('Vendu', $event->description);
        self::assertSame(['Cycle de vie'], $event->categories()->pluck('category')->all());
    }

    #[Test]
    public function record_exit_concatene_la_note_au_motif(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-08-01',
            exitReason: VehicleExitReason::Destroyed,
            note: '  Destruction VHU  ',
        ));

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::FleetExit)
            ->sole();

        self::assertSame('Détruit · Destruction VHU', $event->description);
    }

    #[Test]
    public function record_exit_est_idempotent(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-06-15',
            exitReason: VehicleExitReason::Sold,
            note: null,
        ));
        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-09-30',
            exitReason: VehicleExitReason::Transferred,
            note: null,
        ));

        $events = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::FleetExit)
            ->get();

        self::assertCount(1, $events);
        self::assertSame('2025-09-30', $events->first()->start_date->toDateString());
        self::assertSame('Transféré', $events->first()->description);
    }

    #[Test]
    public function remove_exit_supprime_le_repere_sortie_sans_toucher_l_acquisition(): void
    {
        $vehicle = Vehicle::factory()->create(['acquisition_date' => '2024-03-08']);

        $this->recorder->recordAcquisition($vehicle);
        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-06-15',
            exitReason: VehicleExitReason::Sold,
            note: null,
        ));

        $this->recorder->removeExit($vehicle->id);

        self::assertSame(
            0,
            VehicleEvent::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('system_kind', VehicleEventSystemKind::FleetExit)
                ->count(),
        );
        self::assertSame(
            1,
            VehicleEvent::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('system_kind', VehicleEventSystemKind::Acquisition)
                ->count(),
        );
    }

    #[Test]
    public function record_exit_porte_le_cout_de_sortie(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->recorder->recordExit($vehicle, new ExitVehicleData(
            exitDate: '2025-08-01',
            exitReason: VehicleExitReason::Destroyed,
            note: null,
            amountCents: 45000,
        ));

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::FleetExit)
            ->sole();

        self::assertSame(45000, $event->amount_cents);
    }

    #[Test]
    public function record_acquisition_porte_le_prix_d_achat(): void
    {
        $vehicle = Vehicle::factory()->create(['acquisition_date' => '2024-03-08']);

        $this->recorder->recordAcquisition($vehicle, 2_500_000);

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::Acquisition)
            ->sole();

        self::assertSame(2_500_000, $event->amount_cents);
    }

    #[Test]
    public function record_acquisition_preserve_le_cout_a_la_resync_sans_montant(): void
    {
        // Création avec prix d'achat, puis resync (ex. correction de la date
        // d'acquisition) sans montant explicite : le coût ne doit pas être perdu.
        $vehicle = Vehicle::factory()->create(['acquisition_date' => '2024-03-08']);
        $this->recorder->recordAcquisition($vehicle, 2_500_000);

        $vehicle->acquisition_date = Carbon::parse('2024-05-20');
        $this->recorder->recordAcquisition($vehicle);

        $event = VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('system_kind', VehicleEventSystemKind::Acquisition)
            ->sole();

        self::assertSame('2024-05-20', $event->start_date->toDateString());
        self::assertSame(2_500_000, $event->amount_cents);
    }
}

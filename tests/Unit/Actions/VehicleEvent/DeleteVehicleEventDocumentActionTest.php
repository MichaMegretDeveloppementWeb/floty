<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\VehicleEvent;

use App\Actions\VehicleEvent\DeleteVehicleEventDocumentAction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeleteVehicleEventDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteVehicleEventDocumentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->action = $this->app->make(DeleteVehicleEventDocumentAction::class);
    }

    #[Test]
    public function execute_supprime_le_record_db_et_le_fichier_physique(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $storagePath = "unavailability-documents/{$vehicleEvent->id}/test.pdf";
        Storage::disk(config('filesystems.default'))->put($storagePath, 'fake content');

        $document = VehicleEventDocument::factory()->forVehicleEvent($vehicleEvent)->create([
            'uploaded_by' => $user->id,
            'storage_path' => $storagePath,
        ]);

        $this->action->execute($document);

        $this->assertDatabaseMissing('vehicle_event_documents', ['id' => $document->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($storagePath);
    }

    #[Test]
    public function execute_reste_idempotent_si_le_fichier_physique_est_deja_manquant(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $document = VehicleEventDocument::factory()->forVehicleEvent($vehicleEvent)->create([
            'uploaded_by' => $user->id,
            'storage_path' => "unavailability-documents/{$vehicleEvent->id}/ghost.pdf",
        ]);

        $this->action->execute($document);

        $this->assertDatabaseMissing('vehicle_event_documents', ['id' => $document->id]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\VehicleEvent;

use App\Actions\VehicleEvent\UploadVehicleEventDocumentAction;
use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepositoryInterface;
use App\Exceptions\VehicleEvent\TooManyVehicleEventDocumentsException;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadVehicleEventDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private UploadVehicleEventDocumentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->action = $this->app->make(UploadVehicleEventDocumentAction::class);
    }

    #[Test]
    public function execute_stocke_un_pdf_et_cree_le_record(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('justificatif.pdf', 200, 'application/pdf');

        $document = $this->action->execute(
            vehicleEvent: $vehicleEvent,
            file: $file,
            uploadedByUserId: $user->id,
        );

        $this->assertSame('justificatif.pdf', $document->filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($vehicleEvent->id, $document->vehicle_event_id);
        $this->assertSame($user->id, $document->uploaded_by);
        Storage::disk(config('filesystems.default'))->assertExists($document->storage_path);
        $this->assertStringEndsWith('.pdf', $document->storage_path);
    }

    #[Test]
    public function execute_stocke_un_jpg_avec_extension_preservee(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        $document = $this->action->execute(
            vehicleEvent: $vehicleEvent,
            file: $file,
            uploadedByUserId: $user->id,
        );

        $this->assertSame('photo.jpg', $document->filename);
        $this->assertStringStartsWith('image/', $document->mime_type);
        $this->assertStringEndsWith('.jpg', $document->storage_path);
        Storage::disk(config('filesystems.default'))->assertExists($document->storage_path);
    }

    #[Test]
    public function execute_leve_too_many_documents_au_sixieme(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        VehicleEventDocument::factory()->count(5)->forVehicleEvent($vehicleEvent)->create([
            'uploaded_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');

        $this->expectException(TooManyVehicleEventDocumentsException::class);

        $this->action->execute(
            vehicleEvent: $vehicleEvent,
            file: $file,
            uploadedByUserId: $user->id,
        );
    }

    #[Test]
    public function execute_supprime_le_fichier_si_la_persistance_db_echoue(): void
    {
        $user = User::factory()->create();
        $vehicleEvent = VehicleEvent::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        // Writer throw à l'écriture : le rollback doit nettoyer le disque.
        $writer = new class implements VehicleEventDocumentWriteRepositoryInterface
        {
            public function create(array $row): VehicleEventDocument
            {
                throw new \RuntimeException('simulated DB failure');
            }

            public function delete(int $id): void {}
        };

        $this->app->instance(
            VehicleEventDocumentWriteRepositoryInterface::class,
            $writer,
        );

        $action = $this->app->make(UploadVehicleEventDocumentAction::class);

        $file = UploadedFile::fake()->create('rollback.pdf', 100, 'application/pdf');

        $disk = Storage::disk(config('filesystems.default'));
        $filesBeforeExecution = $disk->allFiles();

        try {
            $action->execute(
                vehicleEvent: $vehicleEvent,
                file: $file,
                uploadedByUserId: $user->id,
            );
            $this->fail('Expected RuntimeException not thrown.');
        } catch (\RuntimeException) {
        }

        $filesAfterExecution = $disk->allFiles();
        $this->assertSame(
            $filesBeforeExecution,
            $filesAfterExecution,
            'Le fichier physique aurait dû être supprimé après l\'échec DB',
        );
    }
}

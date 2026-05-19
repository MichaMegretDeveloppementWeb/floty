<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Unavailability;

use App\Actions\Unavailability\UploadUnavailabilityDocumentAction;
use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Exceptions\Unavailability\TooManyUnavailabilityDocumentsException;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UploadUnavailabilityDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private UploadUnavailabilityDocumentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->action = $this->app->make(UploadUnavailabilityDocumentAction::class);
    }

    #[Test]
    public function execute_stocke_un_pdf_et_cree_le_record(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('justificatif.pdf', 200, 'application/pdf');

        $document = $this->action->execute(
            unavailability: $unavailability,
            file: $file,
            uploadedByUserId: $user->id,
        );

        $this->assertSame('justificatif.pdf', $document->filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($unavailability->id, $document->unavailability_id);
        $this->assertSame($user->id, $document->uploaded_by);
        Storage::disk(config('filesystems.default'))->assertExists($document->storage_path);
        $this->assertStringEndsWith('.pdf', $document->storage_path);
    }

    #[Test]
    public function execute_stocke_un_jpg_avec_extension_preservee(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        $document = $this->action->execute(
            unavailability: $unavailability,
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
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        UnavailabilityDocument::factory()->count(5)->forUnavailability($unavailability)->create([
            'uploaded_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');

        $this->expectException(TooManyUnavailabilityDocumentsException::class);

        $this->action->execute(
            unavailability: $unavailability,
            file: $file,
            uploadedByUserId: $user->id,
        );
    }

    #[Test]
    public function execute_supprime_le_fichier_si_la_persistance_db_echoue(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        // Writer throw à l'écriture : le rollback doit nettoyer le disque.
        $writer = new class implements UnavailabilityDocumentWriteRepositoryInterface
        {
            public function create(array $row): UnavailabilityDocument
            {
                throw new \RuntimeException('simulated DB failure');
            }

            public function delete(int $id): void {}
        };

        $this->app->instance(
            UnavailabilityDocumentWriteRepositoryInterface::class,
            $writer,
        );

        $action = $this->app->make(UploadUnavailabilityDocumentAction::class);

        $file = UploadedFile::fake()->create('rollback.pdf', 100, 'application/pdf');

        $disk = Storage::disk(config('filesystems.default'));
        $filesBeforeExecution = $disk->allFiles();

        try {
            $action->execute(
                unavailability: $unavailability,
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

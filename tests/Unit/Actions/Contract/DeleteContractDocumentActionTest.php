<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\DeleteContractDocumentAction;
use App\Actions\Contract\UploadContractDocumentAction;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Doctrine : DB d'abord, filesystem ensuite (best-effort).
 * Si la suppression DB échoue, on remonte sans toucher au disque.
 */
final class DeleteContractDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    #[Test]
    public function execute_supprime_db_puis_fichier(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $upload = $this->app->make(UploadContractDocumentAction::class);
        $document = $upload->execute(
            contract: $contract,
            file: UploadedFile::fake()->create('a-supprimer.pdf', 200, 'application/pdf'),
            uploadedByUserId: $user->id,
        );

        $storagePath = $document->storage_path;
        Storage::disk(config('filesystems.default'))->assertExists($storagePath);

        $action = $this->app->make(DeleteContractDocumentAction::class);
        $action->execute($document);

        $this->assertDatabaseMissing('contract_documents', ['id' => $document->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($storagePath);
    }

    #[Test]
    public function execute_ne_supprime_pas_le_fichier_si_le_delete_db_echoue(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $upload = $this->app->make(UploadContractDocumentAction::class);
        $document = $upload->execute(
            contract: $contract,
            file: UploadedFile::fake()->create('protege.pdf', 200, 'application/pdf'),
            uploadedByUserId: $user->id,
        );

        $storagePath = $document->storage_path;

        $writerMock = $this->createMock(ContractDocumentWriteRepositoryInterface::class);
        $writerMock->expects($this->once())
            ->method('delete')
            ->with($document->id)
            ->willThrowException(new RuntimeException('DB delete failed'));

        $this->app->instance(ContractDocumentWriteRepositoryInterface::class, $writerMock);
        $action = $this->app->make(DeleteContractDocumentAction::class);

        try {
            $action->execute($document);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('DB delete failed', $e->getMessage());
        }

        Storage::disk(config('filesystems.default'))->assertExists($storagePath);
    }
}

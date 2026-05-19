<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\UploadContractDocumentAction;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Exceptions\Contract\TooManyContractDocumentsException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class UploadContractDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private UploadContractDocumentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->action = $this->app->make(UploadContractDocumentAction::class);
    }

    #[Test]
    public function execute_stocke_le_fichier_et_cree_le_record(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('contrat.pdf', 200, 'application/pdf');

        $document = $this->action->execute(
            contract: $contract,
            file: $file,
            uploadedByUserId: $user->id,
        );

        $this->assertSame('contrat.pdf', $document->filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($contract->id, $document->contract_id);
        $this->assertSame($user->id, $document->uploaded_by);
        Storage::disk(config('filesystems.default'))->assertExists($document->storage_path);
    }

    #[Test]
    public function execute_leve_too_many_documents_au_sixieme(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        ContractDocument::factory()->count(5)->forContract($contract)->create([
            'uploaded_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');

        $this->expectException(TooManyContractDocumentsException::class);

        $this->action->execute(
            contract: $contract,
            file: $file,
            uploadedByUserId: $user->id,
        );
    }

    #[Test]
    public function execute_compense_le_fichier_si_la_persistance_db_echoue(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $writerMock = $this->createMock(ContractDocumentWriteRepositoryInterface::class);
        $writerMock->expects($this->once())
            ->method('create')
            ->willThrowException(new RuntimeException('DB write failed'));

        $this->app->instance(ContractDocumentWriteRepositoryInterface::class, $writerMock);
        $action = $this->app->make(UploadContractDocumentAction::class);

        $file = UploadedFile::fake()->create('contrat.pdf', 200, 'application/pdf');

        try {
            $action->execute(
                contract: $contract,
                file: $file,
                uploadedByUserId: $user->id,
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('DB write failed', $e->getMessage());
        }

        Storage::disk(config('filesystems.default'))
            ->assertDirectoryEmpty("contract-documents/{$contract->id}");

        $this->assertDatabaseCount('contract_documents', 0);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Contract;

use App\Actions\Contract\UploadContractDocumentAction;
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
use Tests\TestCase;

/**
 * Tests Unit de l'Action UploadContractDocumentAction (chantier 04.N).
 *
 * Vérifie la limite V1 des 5 documents par contrat (lève
 * `TooManyContractDocumentsException`) et le happy path (stockage
 * physique + persistance DB).
 */
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

        // Pré-pose 5 documents (limite V1).
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
}

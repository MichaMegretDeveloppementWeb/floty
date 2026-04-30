<?php

declare(strict_types=1);

namespace Tests\Feature\User\Contract;

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
 * Tests Feature des endpoints documents PDF (chantier 04.N).
 *
 * Utilise `Storage::fake()` pour vérifier les writes sur disk sans
 * polluer le filesystem réel.
 */
final class ContractDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    #[Test]
    public function upload_pdf_valide_cree_le_document_et_le_fichier(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $pdf = UploadedFile::fake()->create('contrat-signe.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)
            ->post("/app/contracts/{$contract->id}/documents", ['file' => $pdf]);

        $response->assertCreated()
            ->assertJsonStructure([
                'document' => ['id', 'filename', 'sizeBytes', 'sizeFormatted', 'uploadedAt', 'downloadUrl'],
            ]);

        $this->assertDatabaseHas('contract_documents', [
            'contract_id' => $contract->id,
            'filename' => 'contrat-signe.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ]);

        // Vérifie que le fichier physique existe sur le disk fake.
        $doc = ContractDocument::query()->where('contract_id', $contract->id)->firstOrFail();
        Storage::disk(config('filesystems.default'))->assertExists($doc->storage_path);
    }

    #[Test]
    public function upload_refuse_un_fichier_non_pdf(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $jpg = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($user)
            ->post(
                "/app/contracts/{$contract->id}/documents",
                ['file' => $jpg],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('contract_documents', 0);
    }

    #[Test]
    public function upload_refuse_un_fichier_de_plus_de_10mo(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        // 11 Mo > 10 Mo
        $tooLarge = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

        $this->actingAs($user)
            ->post(
                "/app/contracts/{$contract->id}/documents",
                ['file' => $tooLarge],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('contract_documents', 0);
    }

    #[Test]
    public function upload_refuse_le_sixieme_document(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        // Pré-pose 5 documents.
        ContractDocument::factory()->count(5)->forContract($contract)->create([
            'uploaded_by' => $user->id,
        ]);

        $pdf = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');

        // Accept JSON pour que BaseAppException renvoie un 422 JSON
        // (sinon back() redirige avec un toast → 302).
        $response = $this->actingAs($user)
            ->post(
                "/app/contracts/{$contract->id}/documents",
                ['file' => $pdf],
                ['Accept' => 'application/json'],
            );

        $response->assertStatus(422);
        $this->assertSame(5, ContractDocument::query()->where('contract_id', $contract->id)->count());
    }

    #[Test]
    public function download_renvoie_le_fichier_avec_filename_original(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        // Upload via le endpoint pour avoir un fichier réel sur le disk fake.
        $this->actingAs($user)
            ->post("/app/contracts/{$contract->id}/documents", [
                'file' => UploadedFile::fake()->create('mon-contrat.pdf', 200, 'application/pdf'),
            ])
            ->assertCreated();

        $doc = ContractDocument::query()->where('contract_id', $contract->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->get("/app/contracts/{$contract->id}/documents/{$doc->id}");

        $response->assertOk();
        $this->assertStringContainsString('mon-contrat.pdf', $response->headers->get('content-disposition') ?? '');
    }

    #[Test]
    public function delete_supprime_le_document_et_le_fichier_physique(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $this->actingAs($user)
            ->post("/app/contracts/{$contract->id}/documents", [
                'file' => UploadedFile::fake()->create('a-supprimer.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $doc = ContractDocument::query()->where('contract_id', $contract->id)->firstOrFail();
        $storedPath = $doc->storage_path;

        Storage::disk(config('filesystems.default'))->assertExists($storedPath);

        $this->actingAs($user)
            ->delete("/app/contracts/{$contract->id}/documents/{$doc->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('contract_documents', ['id' => $doc->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($storedPath);
    }

    #[Test]
    public function download_renvoie_404_si_document_n_appartient_pas_au_contrat(): void
    {
        $user = User::factory()->create();
        $contractA = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();
        $contractB = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create();

        $doc = ContractDocument::factory()->forContract($contractA)->create([
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/app/contracts/{$contractB->id}/documents/{$doc->id}")
            ->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\User\Unavailability;

use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature du UnavailabilityDocumentController (P1).
 *
 * Vérifie · auth obligatoire (302 redirect vers login si guest), download
 * stream avec bon nom de fichier, 404 quand le document n'appartient pas à
 * l'indispo dans l'URL, suppression propre (DB + fichier).
 */
final class UnavailabilityDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.default');
        Storage::fake($this->disk);
    }

    #[Test]
    public function store_redirige_si_lutilisateur_nest_pas_connecte(): void
    {
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('justif.pdf', 50, 'application/pdf');

        $response = $this->post("/app/unavailabilities/{$unavailability->id}/documents", [
            'file' => $file,
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function store_cree_un_document_quand_lutilisateur_est_connecte(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('justif.pdf', 50, 'application/pdf');

        $response = $this->actingAs($user)->postJson(
            "/app/unavailabilities/{$unavailability->id}/documents",
            ['file' => $file],
        );

        $response->assertCreated();
        $response->assertJsonStructure(['document' => ['id', 'filename', 'downloadUrl', 'isImage']]);
        $this->assertDatabaseCount('unavailability_documents', 1);
    }

    #[Test]
    public function store_rejette_un_fichier_au_mime_invalide(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $file = UploadedFile::fake()->create('virus.exe', 50, 'application/x-msdownload');

        $response = $this->actingAs($user)->postJson(
            "/app/unavailabilities/{$unavailability->id}/documents",
            ['file' => $file],
        );

        $response->assertUnprocessable();
        $this->assertDatabaseCount('unavailability_documents', 0);
    }

    #[Test]
    public function show_renvoie_404_quand_le_document_napparttient_pas_a_lindispo_de_lurl(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $unavailabilityA = Unavailability::factory()->for($vehicle)->create();
        $unavailabilityB = Unavailability::factory()->for($vehicle)->create();

        $document = UnavailabilityDocument::factory()
            ->forUnavailability($unavailabilityB)
            ->create(['uploaded_by' => $user->id]);

        // getJson · le handler global convertit les 404 HTML en redirect
        // vers le domaine (UX) · on veut tester la sémantique HTTP brute.
        $response = $this->actingAs($user)
            ->getJson("/app/unavailabilities/{$unavailabilityA->id}/documents/{$document->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function show_stream_le_fichier_avec_le_filename_original(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $storagePath = "unavailability-documents/{$unavailability->id}/abc.pdf";
        Storage::disk($this->disk)->put($storagePath, 'PDF binary content');

        $document = UnavailabilityDocument::factory()->forUnavailability($unavailability)->create([
            'uploaded_by' => $user->id,
            'filename' => 'contrat-resiliation.pdf',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)
            ->get("/app/unavailabilities/{$unavailability->id}/documents/{$document->id}");

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('contrat-resiliation.pdf', $response->headers->get('content-disposition'));
    }

    #[Test]
    public function destroy_supprime_le_document_et_renvoie_204(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $storagePath = "unavailability-documents/{$unavailability->id}/x.pdf";
        Storage::disk($this->disk)->put($storagePath, 'content');

        $document = UnavailabilityDocument::factory()->forUnavailability($unavailability)->create([
            'uploaded_by' => $user->id,
            'storage_path' => $storagePath,
        ]);

        $response = $this->actingAs($user)
            ->delete("/app/unavailabilities/{$unavailability->id}/documents/{$document->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('unavailability_documents', ['id' => $document->id]);
        Storage::disk($this->disk)->assertMissing($storagePath);
    }

    #[Test]
    public function destroy_renvoie_404_quand_le_document_napparttient_pas_a_lindispo_de_lurl(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $unavailabilityA = Unavailability::factory()->for($vehicle)->create();
        $unavailabilityB = Unavailability::factory()->for($vehicle)->create();

        $document = UnavailabilityDocument::factory()->forUnavailability($unavailabilityB)->create([
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/app/unavailabilities/{$unavailabilityA->id}/documents/{$document->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('unavailability_documents', ['id' => $document->id]);
    }
}

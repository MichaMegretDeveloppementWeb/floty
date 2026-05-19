<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Unavailability;

use App\Services\Unavailability\UnavailabilityDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit de UnavailabilityDocumentStorage (P1).
 *
 * Couvre les comportements défensifs : safeExtension whitelist + fallback
 * MIME, safeDelete idempotence.
 */
final class UnavailabilityDocumentStorageTest extends TestCase
{
    private UnavailabilityDocumentStorage $storage;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = config('filesystems.default');
        Storage::fake($this->disk);
        $this->storage = new UnavailabilityDocumentStorage;
    }

    #[Test]
    public function store_preserve_lextension_pdf_quand_le_fichier_a_une_extension_valide(): void
    {
        $file = UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf');
        $meta = $this->storage->store($file, unavailabilityId: 42);

        $this->assertStringEndsWith('.pdf', $meta['storage_path']);
        $this->assertStringContainsString('unavailability-documents/42/', $meta['storage_path']);
        $this->assertSame('facture.pdf', $meta['filename']);
        $this->assertSame('application/pdf', $meta['mime_type']);
        Storage::disk($this->disk)->assertExists($meta['storage_path']);
    }

    #[Test]
    public function store_fallback_sur_le_mime_quand_lextension_est_invalide(): void
    {
        // Fichier avec extension exotique mais mime déclaré valide : le
        // fallback `safeExtension` doit traduire image/jpeg → .jpg.
        $file = UploadedFile::fake()->create('sans-extension.xyz', 50, 'image/jpeg');

        $meta = $this->storage->store($file, unavailabilityId: 7);

        $this->assertStringEndsWith('.jpg', $meta['storage_path']);
    }

    #[Test]
    public function safe_delete_navale_silencieusement_un_path_inexistant(): void
    {
        // Aucune exception attendue : safeDelete avale les erreurs driver
        // (test documentaire : l'absence d'exception levée vaut assertion).
        $this->expectNotToPerformAssertions();

        $this->storage->safeDelete('does/not/exist.pdf');
    }

    #[Test]
    public function safe_delete_supprime_un_fichier_existant(): void
    {
        $path = 'unavailability-documents/1/exists.pdf';
        Storage::disk($this->disk)->put($path, 'content');

        $this->storage->safeDelete($path);

        Storage::disk($this->disk)->assertMissing($path);
    }
}

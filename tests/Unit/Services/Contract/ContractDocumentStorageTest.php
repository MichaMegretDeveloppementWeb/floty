<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contract;

use App\Services\Contract\ContractDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit du service ContractDocumentStorage (chantier γ.2).
 *
 * Couvre la sémantique `safeDelete` : avale toute exception filesystem
 * et logge un warning, garant qu'un échec disque ne casse pas une
 * opération métier déjà committée en DB.
 */
final class ContractDocumentStorageTest extends TestCase
{
    private string $disk;

    private ContractDocumentStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = (string) config('filesystems.default');
        Storage::fake($this->disk);
        $this->storage = new ContractDocumentStorage;
    }

    #[Test]
    public function safe_delete_supprime_un_fichier_existant(): void
    {
        Storage::disk($this->disk)->put('contract-documents/1/file.pdf', 'pdf content');

        $this->storage->safeDelete('contract-documents/1/file.pdf');

        Storage::disk($this->disk)->assertMissing('contract-documents/1/file.pdf');
    }

    #[Test]
    public function safe_delete_est_idempotent_sur_fichier_inexistant(): void
    {
        // Pas de fichier au préalable, pas d'exception attendue.
        $this->storage->safeDelete('contract-documents/999/missing.pdf');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function safe_delete_avale_les_exceptions_driver_et_logge_un_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'ContractDocumentStorage::safeDelete failed'
                && ($context['storage_path'] ?? null) === 'contract-documents/1/broken.pdf'
                && ($context['exception'] ?? null) === UnableToDeleteFile::class);

        // Simule un driver qui throw (ex. S3 timeout, permission denied).
        Storage::shouldReceive('disk')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('delete')
            ->once()
            ->with('contract-documents/1/broken.pdf')
            ->andThrow(UnableToDeleteFile::atLocation('contract-documents/1/broken.pdf', 'I/O error'));

        // Pas d'exception attendue : safeDelete doit avaler.
        $this->storage->safeDelete('contract-documents/1/broken.pdf');
    }

    #[Test]
    public function store_et_delete_round_trip(): void
    {
        $file = UploadedFile::fake()->create('demo.pdf', 100, 'application/pdf');

        $meta = $this->storage->store($file, contractId: 42);

        $this->assertSame('demo.pdf', $meta['filename']);
        $this->assertSame('application/pdf', $meta['mime_type']);
        Storage::disk($this->disk)->assertExists($meta['storage_path']);

        $this->storage->delete($meta['storage_path']);

        Storage::disk($this->disk)->assertMissing($meta['storage_path']);
    }
}

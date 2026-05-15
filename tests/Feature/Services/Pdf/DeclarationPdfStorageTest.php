<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Services\Pdf\DeclarationPdfStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lot 5 D10 (F-19-016) · valide que `DeclarationPdfStorage` lit le
 * disque depuis la config Floty plutôt que d'un default codé en dur,
 * permettant la bascule env-driven vers S3/GCS en prod.
 */
final class DeclarationPdfStorageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function utilise_le_disque_configure_via_floty_declarations(): void
    {
        // Configure un disque temporaire identifié, distinct du default,
        // et vérifie que le storage écrit bien sur celui-ci après
        // résolution via le binding du provider.
        Storage::fake('lot5-d10-fake');
        config(['floty.declarations.pdf_storage_disk' => 'lot5-d10-fake']);

        $declaration = FiscalDeclaration::factory()
            ->forCompany(Company::factory()->create())
            ->forYear(2025)
            ->generated()
            ->create();

        $storage = $this->app->make(DeclarationPdfStorage::class);
        $result = $storage->store($declaration, 'BINARY-PDF');

        Storage::disk('lot5-d10-fake')->assertExists($result['path']);
        self::assertSame(hash('sha256', 'BINARY-PDF'), $result['hash']);
    }

    #[Test]
    public function fallback_local_si_config_absente(): void
    {
        // Garde-fou · si l'env DECLARATIONS_PDF_DISK est absent et la
        // config aussi, le service tombe sur 'local' (compat dev/CI).
        Storage::fake('local');
        config(['floty.declarations.pdf_storage_disk' => null]);

        $declaration = FiscalDeclaration::factory()
            ->forCompany(Company::factory()->create())
            ->forYear(2025)
            ->generated()
            ->create();

        $storage = $this->app->make(DeclarationPdfStorage::class);
        $result = $storage->store($declaration, 'BINARY-PDF-FALLBACK');

        Storage::disk('local')->assertExists($result['path']);
    }
}

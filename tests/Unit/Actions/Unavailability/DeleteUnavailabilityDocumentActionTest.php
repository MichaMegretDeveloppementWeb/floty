<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Unavailability;

use App\Actions\Unavailability\DeleteUnavailabilityDocumentAction;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeleteUnavailabilityDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteUnavailabilityDocumentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->action = $this->app->make(DeleteUnavailabilityDocumentAction::class);
    }

    #[Test]
    public function execute_supprime_le_record_db_et_le_fichier_physique(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $storagePath = "unavailability-documents/{$unavailability->id}/test.pdf";
        Storage::disk(config('filesystems.default'))->put($storagePath, 'fake content');

        $document = UnavailabilityDocument::factory()->forUnavailability($unavailability)->create([
            'uploaded_by' => $user->id,
            'storage_path' => $storagePath,
        ]);

        $this->action->execute($document);

        $this->assertDatabaseMissing('unavailability_documents', ['id' => $document->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($storagePath);
    }

    #[Test]
    public function execute_reste_idempotent_si_le_fichier_physique_est_deja_manquant(): void
    {
        $user = User::factory()->create();
        $unavailability = Unavailability::factory()
            ->for(Vehicle::factory()->create())
            ->create();

        $document = UnavailabilityDocument::factory()->forUnavailability($unavailability)->create([
            'uploaded_by' => $user->id,
            'storage_path' => "unavailability-documents/{$unavailability->id}/ghost.pdf",
        ]);

        $this->action->execute($document);

        $this->assertDatabaseMissing('unavailability_documents', ['id' => $document->id]);
    }
}

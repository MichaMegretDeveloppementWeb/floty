<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Assignment;

use App\Actions\Assignment\BulkCreateAssignmentsAction;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de l'orchestration shape des rows + délégation au repo. Les
 * cas d'unicité (couple × date actif déjà attribué) sont couverts par
 * `tests/Feature/Assignment/AssignmentUniqueTriggerTest`.
 */
final class BulkCreateAssignmentsActionTest extends TestCase
{
    use RefreshDatabase;

    private BulkCreateAssignmentsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(BulkCreateAssignmentsAction::class);
    }

    #[Test]
    public function insere_n_lignes_pour_un_couple_avec_driver_id_null(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $result = $this->action->execute(
            $vehicle->id,
            $company->id,
            ['2024-01-01', '2024-01-02', '2024-01-03'],
        );

        self::assertSame(3, $result->requested);
        self::assertSame(3, $result->inserted);
        self::assertSame(0, $result->skipped);

        self::assertSame(3, DB::table('assignments')->count());
        $rows = DB::table('assignments')->get();
        foreach ($rows as $row) {
            self::assertSame($vehicle->id, $row->vehicle_id);
            self::assertSame($company->id, $row->company_id);
            self::assertNull($row->driver_id);
            self::assertNotNull($row->created_at);
            self::assertSame($row->created_at, $row->updated_at);
        }
    }

    #[Test]
    public function partage_les_memes_timestamps_entre_les_rows_d_un_meme_bulk(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $this->action->execute($vehicle->id, $company->id, ['2024-02-01', '2024-02-02']);

        $timestamps = DB::table('assignments')->pluck('created_at')->unique();
        self::assertCount(1, $timestamps, 'Toutes les rows du bulk doivent partager le même created_at.');
    }

    #[Test]
    public function tableau_vide_renvoie_zero_sans_lever(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $result = $this->action->execute($vehicle->id, $company->id, []);

        self::assertSame(0, $result->requested);
        self::assertSame(0, $result->inserted);
        self::assertSame(0, $result->skipped);
    }
}

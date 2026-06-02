<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fou cleanup migrations (bonus-A · C4.b).
 *
 * Asserte la liste exacte des tables produites par `migrate:fresh`
 * après merge des ALTER dans les CREATE. Toute introduction d'une
 * nouvelle table fera échouer ce test · forcera le développeur à
 * mettre à jour la liste explicitement, plutôt que de laisser
 * dériver le schéma en silence.
 */
final class MigrationsCleanlinessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrate_fresh_produit_exactement_les_tables_attendues(): void
    {
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->reject(fn ($t) => $t === 'migrations')
            ->sort()
            ->values()
            ->all();

        $expected = [
            'billing_settings',
            'cache',
            'cache_locks',
            'companies',
            'contract_documents',
            'contract_drivers',
            'contracts',
            'driver_company',
            'drivers',
            // Tables `failed_jobs`, `job_batches`, `jobs` retirées · pas de queue
            // async en V1, `QUEUE_CONNECTION=sync` partout. Si V2 introduit la
            // queue, `php artisan queue:table` régénère la migration.
            'fiscal_declarations',
            'fiscal_review_decisions',
            'fiscal_risk_settings',
            'fiscal_rules',
            'invoice_lines',
            'invoices',
            'password_reset_tokens',
            'rental_discount_vehicles',
            'rental_discounts',
            'sessions',
            'unavailabilities',
            'unavailability_documents',
            'users',
            'vehicle_fiscal_characteristics',
            'vehicle_yearly_pricings',
            'vehicles',
        ];

        sort($expected);

        $this->assertSame($expected, $tables);
    }
}

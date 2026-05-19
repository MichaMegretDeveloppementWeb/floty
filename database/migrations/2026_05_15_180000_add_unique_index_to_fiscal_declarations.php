<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enforces "at most one active declaration per (company_id, fiscal_year)" at the DB level.
 * MySQL has no partial UNIQUE indexes: we emulate via a virtual generated column
 * that equals CONCAT(company_id, '-', fiscal_year) only while active (else NULL),
 * indexed UNIQUE (NULLs are tolerated by SQL standard).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('
            ALTER TABLE fiscal_declarations
            ADD COLUMN active_uniqueness_key VARCHAR(64)
            GENERATED ALWAYS AS (
                CASE
                    WHEN deleted_at IS NULL AND is_obsolete = 0
                    THEN CONCAT(company_id, "-", fiscal_year)
                    ELSE NULL
                END
            ) VIRTUAL
        ');

        DB::statement('
            ALTER TABLE fiscal_declarations
            ADD CONSTRAINT decl_active_uniqueness UNIQUE (active_uniqueness_key)
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE fiscal_declarations DROP INDEX decl_active_uniqueness');
        DB::statement('ALTER TABLE fiscal_declarations DROP COLUMN active_uniqueness_key');
    }
};

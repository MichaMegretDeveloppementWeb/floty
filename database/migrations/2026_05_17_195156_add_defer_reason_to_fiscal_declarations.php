<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds defer_reason on fiscal_declarations: optional free text shown while the
 * declaration is in the deferred status. Cleared on revert or on generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->text('defer_reason')
                ->nullable()
                ->after('obsolete_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->dropColumn('defer_reason');
        });
    }
};

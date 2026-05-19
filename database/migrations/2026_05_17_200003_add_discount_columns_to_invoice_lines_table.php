<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds gross + discount + applied_discount_id columns on invoice_lines (snapshot).
 * applied_discount_id is nullOnDelete so a hard discount deletion does not break
 * issued invoices. Backfill: gross = net, discount = 0 for pre-feature rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->unsignedInteger('gross_total_cents')->default(0)->after('total_ht_cents');
            $table->unsignedInteger('discount_cents')->default(0)->after('gross_total_cents');

            $table->foreignId('applied_discount_id')
                ->nullable()
                ->after('discount_cents')
                ->constrained('rental_discounts')
                ->nullOnDelete();
        });

        // Backfill: gross = net for pre-feature lines.
        DB::statement('UPDATE invoice_lines SET gross_total_cents = total_ht_cents');
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropForeign(['applied_discount_id']);
            $table->dropColumn(['gross_total_cents', 'discount_cents', 'applied_discount_id']);
        });
    }
};

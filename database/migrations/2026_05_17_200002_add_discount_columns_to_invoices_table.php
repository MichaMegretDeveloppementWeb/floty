<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds gross + discount snapshot columns on invoices. total_ht_cents stays the NET.
 * Existing invoices are backfilled with gross = net and discount = 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('total_gross_cents')->default(0)->after('total_ht_cents');
            $table->unsignedInteger('total_discount_cents')->default(0)->after('total_gross_cents');
        });

        // Backfill: gross = net, discount = 0 for pre-feature invoices.
        DB::statement('UPDATE invoices SET total_gross_cents = total_ht_cents');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['total_gross_cents', 'total_discount_cents']);
        });
    }
};

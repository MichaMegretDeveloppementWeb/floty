<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot label and basis points of the applied rental discount on invoice_lines
 * (ADR-0008 immutability). The FK is kept for navigation; the snapshot survives
 * label edits or hard deletion of the discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->string('applied_discount_label_snapshot', 120)
                ->nullable()
                ->after('applied_discount_id');

            $table->unsignedSmallInteger('applied_discount_basis_points_snapshot')
                ->nullable()
                ->after('applied_discount_label_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'applied_discount_label_snapshot',
                'applied_discount_basis_points_snapshot',
            ]);
        });
    }
};

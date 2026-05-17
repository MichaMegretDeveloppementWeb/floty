<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 D13 · ajoute `defer_reason` (TEXT NULL) sur `fiscal_declarations`.
 *
 * Quand l'utilisateur reporte la préparation d'un brouillon (statut
 * `deferred`), il saisit une raison optionnelle (modal textarea max
 * 500 caractères) · cette raison est affichée sur le brouillon tant
 * qu'il reste reporté. Au revert (deferred → draft) ou à la génération
 * (deferred → generated), la raison est effacée · état transitoire
 * cohérent avec le statut, pas un historique persistant.
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

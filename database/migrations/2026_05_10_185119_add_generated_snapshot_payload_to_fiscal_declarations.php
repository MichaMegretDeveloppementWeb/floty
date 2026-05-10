<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistance du snapshot fiscal au moment de la génération
 * (Phase 11 D5.7.5, audit pré-livraison B5).
 *
 * **Pourquoi** : avant cette migration, le `FiscalDeclarationSnapshot`
 * était calculé à chaque consultation de la déclaration via
 * `DeclarationFiscalEngine::compute()`. Si une donnée sous-jacente
 * (contrat, VFC, indispo, décision de revue) est modifiée sans
 * déclencher l'invalidation (`is_obsolete = true`), le recalcul
 * affiche des montants différents du PDF persisté. Risque légal
 * majeur en cas de litige : impossible de prouver « voici exactement
 * ce qui a été calculé le {date} ».
 *
 * **Sémantique** : `generated_snapshot_payload` est posée au moment
 * de `markAsGenerated()` avec la sérialisation JSON du
 * `FiscalDeclarationSnapshotData` (DTO Spatie typé TS). La page Show
 * et l'API future doivent lire en priorité depuis ce champ ; fallback
 * recalcul `compute()` uniquement si null (Draft ou déclarations
 * historiques pré-D5.7.5).
 *
 * **Nullable** : Draft sans payload + rétro-compat. Aucun backfill
 * automatique - les déclarations historiques générées pré-D5.7.5
 * resteront sans payload (pas d'enjeu : V1 dev local).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->json('generated_snapshot_payload')->nullable()->after('generated_pdf_hash');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->dropColumn('generated_snapshot_payload');
        });
    }
};

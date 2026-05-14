<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup F-19-001 · drop tables `declarations` + `declaration_pdfs`.
 *
 * Cf. plan-remediation-vague-1 Lot 1 D9.
 *
 * Le schéma legacy issu de phase 01.bis (`declarations` + `declaration_pdfs`)
 * a été abandonné au profit de `fiscal_declarations` (phase 11, ADR-0015).
 * Aucun code applicatif ne référence plus ces tables · doctrine V1
 * zéro-dette impose le retrait complet plutôt qu'un héritage dormant.
 *
 * Les 2 migrations CREATE originelles (2026_04_24_190007 +
 * 2026_04_24_190008) sont supprimées dans le même changeset · sur
 * `migrate:fresh` les tables ne sont plus créées. Cette migration est
 * un filet pour les bases dev existantes qui auraient déjà appliqué les
 * CREATE avant cleanup · `dropIfExists` garantit l'idempotence.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ordre · `declaration_pdfs` d'abord (FK vers `declarations`),
        // puis `declarations`. `dropIfExists` est tolérant à l'absence
        // (cas fresh DB où les CREATE originelles ont déjà disparu).
        Schema::dropIfExists('declaration_pdfs');
        Schema::dropIfExists('declarations');
    }

    public function down(): void
    {
        // Aucun rollback · schéma legacy abandonné définitivement.
        // Recréer les tables sans modèles ni code applicatif n'aurait
        // aucun usage. Pour récupérer l'historique des CREATE, voir
        // git history avant ce commit.
    }
};

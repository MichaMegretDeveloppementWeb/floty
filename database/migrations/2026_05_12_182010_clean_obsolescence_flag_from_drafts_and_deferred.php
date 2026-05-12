<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 D5.10.O · cleanup du flag `is_obsolete` sur les brouillons.
 *
 * Une déclaration `draft` ou `deferred` est par essence en cours · son
 * périmètre est recalculé live à chaque ouverture en Review (via
 * `DeclarationPreviewService::preview`), donc le marquage obsolète
 * n'apporte aucune information actionable et créait des bugs UX (label
 * faux « Générée · obsolète » sur le header Show, banner d'obsolescence
 * affichée à tort, accès Review bloqué par le redirect controller).
 *
 * Sous la nouvelle doctrine, seules les déclarations `generated`
 * (immuables, peuvent être périmées vs le périmètre actuel) portent le
 * flag. Cette migration nettoie les données existantes pour aligner
 * l'état BDD avec la nouvelle règle appliquée côté service
 * `DeclarationInvalidationDetector`.
 *
 * **Cleanup triplet** · `is_obsolete=false`, `obsolete_at=null`,
 * `obsolete_reasons=null`. La colonne `superseded_by_id` n'est
 * **pas touchée** car elle reste utile pour S7 RegenerationInProgress
 * (chaînage draft successeur d'une generated obsolète).
 *
 * **`down()` no-op** · reconstruire un historique d'obsolescence pour
 * les brouillons serait sémantiquement faux. En cas de rollback, on
 * accepte de perdre cet historique fictif qui n'aurait jamais dû
 * exister.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('fiscal_declarations')
            ->whereIn('status', ['draft', 'deferred'])
            ->where('is_obsolete', true)
            ->update([
                'is_obsolete' => false,
                'obsolete_at' => null,
                'obsolete_reasons' => null,
            ]);
    }

    public function down(): void
    {
        // No-op · cf. docstring · la suppression du flag d'obsolescence
        // sur brouillons est sémantiquement correcte (D5.10.O · un
        // brouillon n'est jamais obsolète). On ne reconstruit pas un
        // historique d'obsolescence qui n'aurait plus de raison d'être.
    }
};

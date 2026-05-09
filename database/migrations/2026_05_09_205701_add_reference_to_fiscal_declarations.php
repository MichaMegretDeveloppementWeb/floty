<?php

declare(strict_types=1);

use App\Services\Fiscal\Declaration\DeclarationReferenceGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numérotation lisible des déclarations (Phase 11 D5.3).
 *
 * Format produit par {@see DeclarationReferenceGenerator}
 * au moment du passage `Draft → Generated` (D5.5) :
 *
 *   `DECL-{shortCode}-{year}-{NNNN}`  (ex. `DECL-ACM-2024-0001`)
 *
 * `NNNN` = compteur séquentiel à 4 chiffres par couple `(company_id,
 * fiscal_year)` ; les soft-deleted comptent pour préserver le séquencement
 * (l'id et la référence ne reculent jamais après obsolescence).
 *
 * **Nullable** : les déclarations en `Draft` n'ont pas de référence ; elle
 * est attribuée uniquement à la génération. Multiple `NULL` simultanés
 * autorisés (plusieurs Draft potentiels - en pratique un seul actif à la
 * fois mais la mécanique d'obsolescence peut en faire cohabiter).
 *
 * **Pas de unique constraint** : multiple `NULL` accepté en SQL standard.
 * L'unicité fonctionnelle de la référence (à `(company, year)` près) est
 * garantie par la mécanique séquentielle du service - pas une contrainte
 * SQL à valider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->string('reference', 32)->nullable()->after('fiscal_year');
            $table->index(
                ['company_id', 'fiscal_year', 'reference'],
                'decl_company_year_reference_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_declarations', function (Blueprint $table): void {
            $table->dropIndex('decl_company_year_reference_idx');
            $table->dropColumn('reference');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assouplit l'unicité `(company_id, year, month)` pour compatibilité
 * SoftDeletes (D5.10.P · option C historique factures).
 *
 * **Problème** : la migration initiale posait
 * `unique(['company_id', 'year', 'month'])` pour garantir qu'il n'existe
 * qu'une seule facture par couple. Après l'introduction du soft-delete
 * pour conserver l'historique des régénérations, une ancienne facture
 * soft-deletée reste en base et bloquerait l'insertion d'une nouvelle
 * facture pour le même couple.
 *
 * **Solution** : on retire l'unique constraint DB et on porte l'unicité
 * au niveau applicatif. {@see GenerateInvoiceAction} vérifie déjà
 * l'absence d'une facture active pour le couple via
 * {@see InvoiceReadRepository::findForCompanyYearMonth} et lève
 * `InvoiceAlreadyExistsException` le cas échéant. L'index ordinaire
 * `inv_company_year_month_idx` (présent dans la migration initiale)
 * reste pour les performances de lookup.
 *
 * **Race condition résiduelle** : deux threads concurrents peuvent en
 * théorie générer chacun une facture pour le même couple avant que
 * l'un ne voie l'autre. Acceptable en V1.2 (faible volumétrie · 1 user)
 * · à durcir par lock pessimiste sur séquenceur si nécessaire en prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (utilisé en tests) ne supporte pas `dropUnique` directement
        // sur un index nommé automatiquement · on cible explicitement.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        // Restaurer l'unique requiert que la table ne contienne aucune
        // facture obsolète/soft-deletée partageant un couple avec une
        // facture active · sinon la migration échoue. On purge d'abord
        // les soft-deleted pour permettre le rollback dev.
        DB::table('invoices')->whereNotNull('deleted_at')->delete();

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['company_id', 'year', 'month']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoices` — Facturation mensuelle d'une entreprise utilisatrice
 * (Phase 14.E V1.2).
 *
 * Une ligne = (entreprise × année × mois civil) facturable. La contrainte
 * UNIQUE garantit qu'on ne génère pas deux fois la facture du même mois
 * pour la même entreprise (cohérent doctrine « factures immuables — pas
 * de regénération auto », cf. mémoire `roadmap_v12_facturation`).
 *
 * Snapshot immuable :
 *   - `invoice_number` : numéro humain stable (ex. `2024-03-FLOTY-0042`)
 *   - `total_ht_cents` : total à la date de génération (figé)
 *   - `pdf_path` : chemin storage local (cf. `InvoicePdfStorage`)
 *   - `pdf_hash` : SHA-256 hex du PDF (intégrité — détecte mutation)
 *   - `generated_at` / `generated_by_user_id` : trail audit
 *
 * Si l'utilisateur modifie un contrat **après** émission de la facture,
 * c'est de sa responsabilité (la facture reste figée). La doctrine
 * scope V1.2 sera révisée si besoin (cf. roadmap, hors scope V1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Numéro humain unique pour affichage. Format
            // `{year}-{month:02d}-{seq:04d}` (ex. `2024-03-0042`).
            // Contrainte UNIQUE séparée par sécurité opérationnelle :
            // il sert d'identifiant communicationnel.
            $table->string('invoice_number', 32);

            $table->unsignedInteger('total_ht_cents');

            // Storage relatif (sous `storage/app/private/invoices/`).
            $table->string('pdf_path', 255);
            // SHA-256 hex = 64 chars.
            $table->string('pdf_hash', 64);

            $table->timestamp('generated_at');
            $table->foreignId('generated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            // 1 facture par couple (entreprise, année, mois) — empêche
            // les doublons applicatifs.
            $table->unique(['company_id', 'year', 'month']);
            $table->unique('invoice_number');

            // Index dédié pour Index lookup `byCompany` filtré.
            $table->index(['company_id', 'year', 'month'], 'inv_company_year_month_idx');
        });

        // CHECK contraintes — MySQL uniquement (SQLite skippe les CHECK
        // post-création de table, cf. migrations VFC + pricings).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE invoices
                ADD CONSTRAINT chk_inv_year_range
                CHECK (year BETWEEN 2020 AND 2099)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE invoices
                ADD CONSTRAINT chk_inv_month_range
                CHECK (month BETWEEN 1 AND 12)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

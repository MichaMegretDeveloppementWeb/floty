<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `contract_documents` - PDF joints à un contrat (chantier 04.N).
 *
 * **Limites métier V1** (matérialisées dans l'Action, pas en DB) :
 *   - 5 documents maximum par contrat
 *   - 10 Mo maximum par fichier
 *   - PDF uniquement (validation MIME)
 *
 * **Stockage physique** : sur le disk Laravel par défaut
 * (`config('filesystems.default')`). En V1 = `local` private. Bascule
 * S3 = juste changer `FILESYSTEM_DISK` dans .env, pas de migration.
 *
 * Path de stockage : `contract-documents/{contract_id}/{uuid}.pdf`
 * - UUID pour éviter collisions, contract_id pour cleanup facile.
 *
 * Pas de soft-delete : la suppression côté UI est immédiate (DB +
 * fichier physique). Choix V1 pour simplifier ; si l'on veut garder
 * un audit trail en V2, on ajoutera un soft-delete + un job de
 * purge planifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_documents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('contract_id')
                ->constrained('contracts')
                ->restrictOnDelete();

            $table->string('filename', 255);
            $table->string('storage_path', 500);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('mime_type', 100);

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['contract_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');
    }
};

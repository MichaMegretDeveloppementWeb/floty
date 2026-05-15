<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `unavailability_documents` · justificatifs joints à une
 * indisponibilité véhicule (P1).
 *
 * **Limites métier V1** (matérialisées dans l'Action, pas en DB) ·
 *   - 5 documents maximum par indisponibilité
 *   - 5 Mo maximum par fichier
 *   - Images (jpg, jpeg, png, webp) ou PDF (validation MIME)
 *
 * **Stockage physique** · sur le disk Laravel par défaut. V1 = `local`
 * private (`storage/app/private/`). Bascule S3 = juste changer
 * `FILESYSTEM_DISK` dans `.env`, pas de migration.
 *
 * Path de stockage · `unavailability-documents/{unavailability_id}/{uuid}.{ext}`
 * - UUID pour éviter collisions, unavailability_id pour cleanup facile,
 *   extension préservée pour distinguer img/PDF côté téléchargement.
 *
 * Pas de soft-delete · cohérent avec ContractDocument. La suppression
 * UI est immédiate (DB + fichier physique). V2 ajoutera un soft-delete
 * + job de purge si besoin d'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unavailability_documents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('unavailability_id')
                ->constrained('unavailabilities')
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

            $table->index(['unavailability_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unavailability_documents');
    }
};

<?php

declare(strict_types=1);

use App\Actions\User\Declaration\GenerateDeclarationPdfAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `declaration_pdfs` — Historique immuable des PDF générés (ADR-0003).
 *
 * Cf. 02-schema-fiscal.md § 3.
 *
 * Chaque génération = une ligne + un fichier sur le filesystem Laravel.
 * **Immutable** : pas de `updated_at`, pas de `deleted_at`. Ajout uniquement,
 * jamais de modification post-création, jamais de suppression.
 *
 * Le `version_number` est calculé applicativement dans une transaction
 * pour éviter les races concurrentes ({@see GenerateDeclarationPdfAction},
 * phase 12). L'UNIQUE (declaration_id, version_number) est un filet SQL.
 *
 * Le `snapshot_sha256` sert à la détection d'invalidation (ADR-0004) :
 * comparaison du hash recalculé sur données courantes vs ce hash figé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaration_pdfs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('declaration_id')
                ->constrained('declarations')
                ->restrictOnDelete();

            $table->string('pdf_path', 500);
            $table->string('pdf_filename');
            $table->unsignedBigInteger('pdf_size_bytes');
            $table->char('pdf_sha256', 64);

            $table->json('snapshot_json');
            $table->char('snapshot_sha256', 64);

            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('version_number');

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['declaration_id', 'version_number']);
            $table->index(['declaration_id', 'version_number']);
            $table->index('generated_at');
            $table->index('snapshot_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_pdfs');
    }
};

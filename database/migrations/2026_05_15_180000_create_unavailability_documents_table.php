<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents for an unavailability. Limits (5 files, 5 Mo each, jpg/png/webp/pdf)
 * enforced in the Action. Storage path: unavailability-documents/{id}/{uuid}.{ext}.
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

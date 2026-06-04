<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B2 · supporting documents attached to a control execution.
 * Mirrors `vehicle_event_documents`: limits (5 files, 5 Mo each, jpg/png/webp/pdf)
 * enforced in the Action. No soft-delete (deletion hard-removes row + file).
 * Storage path: control-execution-documents/{control_execution_id}/{uuid}.{ext}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_execution_documents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('control_execution_id')
                ->constrained('control_executions')
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

            $table->index(['control_execution_id', 'created_at'], 'ced_execution_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_execution_documents');
    }
};

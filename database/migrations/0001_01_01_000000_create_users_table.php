<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `users` Floty (cf. 01-schema-metier.md § 1 + ADR-0012).
 *
 * Écart avec le scaffold Laravel par défaut :
 *   - `name` → split en `first_name` + `last_name` (affichage structuré)
 *   - Ajout de `must_change_password` (forçage au premier login)
 *   - Ajout de `last_login_at` (audit + UX « dernière activité »)
 *   - Ajout de `deleted_at` soft delete (départ d'un gestionnaire préserve
 *     l'historique — cf. ADR-0012 révision 2026-04-24)
 *
 * Les tables associées `password_reset_tokens` et `sessions` restent
 * au format Laravel standard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B · removes the redundant "always notify" recipient scalar from
 * `control_reminder_settings`. It behaved exactly like a level-0 default
 * recipient (same base recipient map, equally excludable by a downstream
 * exclude delta), so the distinction was meaningless. It is merged into the
 * default recipient list and the two columns are dropped.
 *
 * Order matters: the existing scalar is backfilled into a `settings`-level
 * include delta FIRST (no data loss), THEN the columns are dropped. Idempotent:
 * the backfill is skipped when there is no value or when an identical default
 * recipient already exists. Safe on prod (data preserved) and on a fresh DB
 * (the singleton row is created lazily, so there is nothing to backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('control_reminder_settings')->where('id', 1)->first();

        if ($settings !== null && $settings->always_notify_email !== null) {
            $email = mb_strtolower(trim((string) $settings->always_notify_email));

            if ($email !== '') {
                $alreadyDefault = DB::table('control_recipient_deltas')
                    ->where('level', 'settings')
                    ->where('operation', 'include')
                    ->where('email', $email)
                    ->exists();

                if (! $alreadyDefault) {
                    DB::table('control_recipient_deltas')->insert([
                        'level' => 'settings',
                        'control_definition_id' => null,
                        'vehicle_control_override_id' => null,
                        'operation' => 'include',
                        'email' => $email,
                        'name' => $settings->always_notify_name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::table('control_reminder_settings', function (Blueprint $table): void {
            $table->dropColumn(['always_notify_name', 'always_notify_email']);
        });
    }

    public function down(): void
    {
        // Best-effort restore of the columns. The previous scalar value is not
        // reconstructed: it now lives as a `settings`-level default recipient.
        Schema::table('control_reminder_settings', function (Blueprint $table): void {
            $table->string('always_notify_name', 120)->nullable()->after('repeat_every_days');
            $table->string('always_notify_email', 180)->nullable()->after('always_notify_name');
        });
    }
};

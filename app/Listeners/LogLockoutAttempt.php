<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Auth\LoginAttemptService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;

/**
 * Logge sur le canal `auth` chaque déclenchement de rate-limit login.
 *
 * Couplé à l'événement {@see Lockout} dispatché par
 * {@see LoginAttemptService} (cf. ADR-0011 § 3).
 *
 * PII · l'email présent dans la request est haché SHA-256 avant log
 * pour permettre la corrélation forensique sans fuiter l'identité si
 * les fichiers de log sont compromis. Le password n'est jamais logué.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D2 (F-10-002).
 */
final readonly class LogLockoutAttempt
{
    public function handle(Lockout $event): void
    {
        $request = $event->request;
        $rawEmail = $request->input('email');

        Log::channel('auth')->warning('login.lockout', [
            'ip' => $request->ip(),
            'email_hash' => is_string($rawEmail) && $rawEmail !== ''
                ? hash('sha256', mb_strtolower($rawEmail))
                : null,
            'user_agent' => $request->userAgent(),
        ]);
    }
}

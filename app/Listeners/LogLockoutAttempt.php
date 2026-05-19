<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Auth\LoginAttemptService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;

/**
 * Writes a warning entry on the `auth` log channel each time the login
 * rate limiter trips (ADR-0011 § 3). Coupled with the {@see Lockout}
 * event dispatched by {@see LoginAttemptService}.
 *
 * The submitted email is hashed (SHA-256) before logging so forensic
 * correlation remains possible without leaking the identity in case the
 * log files are compromised. The password is never logged.
 */
final readonly class LogLockoutAttempt
{
    /**
     * Log the lockout event with the request IP, hashed email and user
     * agent.
     */
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

<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Sends a password reset link to the given email address.
 *
 * Anti email-enumeration: the internal status returned by Laravel is
 * never propagated to the caller; the controller always shows the same
 * generic message. Without this an attacker could enumerate registered
 * emails (ADR-0011 § 3).
 */
final readonly class SendPasswordResetLinkAction
{
    public function execute(string $email, string $ip): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        Log::channel('auth')->notice('password.reset_requested', [
            'email_hash' => hash('sha256', mb_strtolower($email)),
            'ip' => $ip,
            'status' => $status,
        ]);
    }
}

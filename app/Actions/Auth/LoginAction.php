<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TooManyLoginAttemptsException;
use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a login attempt: rate-limit check, authentication attempt,
 * and on success the failure counter reset plus `last_login_at` stamping.
 *
 * Stateless: session/cookie handling (regenerate, intended redirect)
 * stays in the controller.
 */
final readonly class LoginAction
{
    public function __construct(
        private LoginAttemptService $attempts,
    ) {}

    /**
     * @throws TooManyLoginAttemptsException when the rate-limit is reached
     * @throws InvalidCredentialsException when the credentials are invalid
     */
    public function execute(string $email, string $password, string $ip): User
    {
        $this->attempts->ensureNotRateLimited($email, $ip);

        $emailHash = hash('sha256', mb_strtolower($email));

        if (! Auth::attempt(['email' => $email, 'password' => $password], false)) {
            $this->attempts->recordFailedAttempt($email, $ip);

            // Forensic log: email hashed for correlation without leaking
            // PII if log files are compromised (ADR-0011 § 3).
            Log::channel('auth')->notice('login.failed', [
                'email_hash' => $emailHash,
                'ip' => $ip,
            ]);

            throw InvalidCredentialsException::make();
        }

        $this->attempts->clearAttempts($email, $ip);

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => Date::now()])->save();

        Log::channel('auth')->notice('login.success', [
            'user_id' => $user->id,
            'email_hash' => $emailHash,
            'ip' => $ip,
        ]);

        return $user;
    }
}

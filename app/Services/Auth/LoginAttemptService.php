<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Auth\TooManyLoginAttemptsException;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login rate-limiter (ADR-0011 § 3 rev. 1.1).
 *
 * Two layers ·
 *   - 5 attempts / 2 min per (email + IP) · bruteforce on a specific
 *     account.
 *   - 10 attempts / 2 min per IP · distributed account attacks.
 *
 * The throttle middleware on POST /login (`throttle:10,2`) caps the IP
 * at the same rate ahead of this layer; the in-app IP counter is a
 * defense-in-depth filet plus the source of {@see Lockout} events for
 * audit listeners. Stateless · all counters live in the RateLimiter,
 * easy to reset between tests via `RateLimiter::clear()`.
 */
final class LoginAttemptService
{
    public const int MAX_ATTEMPTS_PER_EMAIL = 5;

    public const int MAX_ATTEMPTS_PER_IP = 10;

    public const int DECAY_SECONDS = 120;

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    /**
     * Throws {@see TooManyLoginAttemptsException} (and emits `Lockout`)
     * when either counter is saturated.
     */
    public function ensureNotRateLimited(string $email, string $ip): void
    {
        $emailKey = $this->emailKey($email, $ip);
        $ipKey = $this->ipKey($ip);

        if (RateLimiter::tooManyAttempts($emailKey, self::MAX_ATTEMPTS_PER_EMAIL)) {
            $this->events->dispatch(new Lockout(request()));

            throw TooManyLoginAttemptsException::forEmail(
                email: $email,
                retryAfterSeconds: RateLimiter::availableIn($emailKey),
            );
        }

        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            $this->events->dispatch(new Lockout(request()));

            throw TooManyLoginAttemptsException::forIp(
                ip: $ip,
                retryAfterSeconds: RateLimiter::availableIn($ipKey),
            );
        }
    }

    /**
     * Increments both counters after a failed attempt.
     */
    public function recordFailedAttempt(string $email, string $ip): void
    {
        RateLimiter::hit($this->emailKey($email, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($ip), self::DECAY_SECONDS);
    }

    /**
     * Resets both counters after a successful login.
     */
    public function clearAttempts(string $email, string $ip): void
    {
        RateLimiter::clear($this->emailKey($email, $ip));
        RateLimiter::clear($this->ipKey($ip));
    }

    private function emailKey(string $email, string $ip): string
    {
        return Str::transliterate(
            'login:email:'.Str::lower($email).'|'.$ip,
        );
    }

    private function ipKey(string $ip): string
    {
        return 'login:ip:'.$ip;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Auth\TooManyLoginAttemptsException;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Gestion du rate-limit sur les tentatives de connexion (ADR-0011).
 *
 * Double couche :
 *   - 5 tentatives / 15 min sur le couple email+IP - anti bruteforce
 *     ciblé d'un compte précis.
 *   - 50 tentatives / 15 min sur l'IP seule - anti attaques distribuées
 *     (un attaquant avec N IPs ne peut pas faire 5×N tentatives par
 *     email).
 *
 * L'événement {@see Lockout} est dispatché à chaque blocage pour
 * permettre aux listeners (audit, alerte sécurité) d'observer.
 *
 * Service stateless - toutes les bornes vivent dans le RateLimiter.
 * Testable sans HTTP via {@see RateLimiter::clear()} dans `setUp`.
 */
final class LoginAttemptService
{
    public const int MAX_ATTEMPTS_PER_EMAIL = 5;

    public const int MAX_ATTEMPTS_PER_IP = 50;

    public const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    /**
     * Vérifie qu'aucune des deux limites n'est atteinte. Lève
     * {@see TooManyLoginAttemptsException} sinon (et émet `Lockout`).
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
     * Incrémente les deux compteurs (email+IP et IP seule).
     */
    public function recordFailedAttempt(string $email, string $ip): void
    {
        RateLimiter::hit($this->emailKey($email, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($ip), self::DECAY_SECONDS);
    }

    /**
     * Reset les deux compteurs après une connexion réussie.
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

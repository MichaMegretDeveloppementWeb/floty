<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Request de connexion — applique le throttle ADR-0011 (5 tentatives / 15 min
 * par couple IP + email, message générique en cas d'échec).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Tente l'authentification. Lève une `ValidationException` avec message
     * générique en cas d'échec — on ne divulgue pas si l'email est inconnu
     * ou si le mot de passe est faux (OWASP, ADR-0011).
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), false)) {
            RateLimiter::hit($this->emailThrottleKey(), decaySeconds: 900);
            RateLimiter::hit($this->ipThrottleKey(), decaySeconds: 900);

            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        RateLimiter::clear($this->emailThrottleKey());
        RateLimiter::clear($this->ipThrottleKey());

        // Trace de la dernière connexion (colonne users.last_login_at).
        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * Double protection rate-limit :
     *   - 5 tentatives / 15 min par couple email+IP — anti bruteforce ciblé.
     *   - 50 tentatives / 15 min par IP seule — anti attaques distribuées
     *     (un attaquant avec N IPs ne peut pas faire 5×N tentatives par
     *     email).
     */
    private function ensureIsNotRateLimited(): void
    {
        $emailKey = $this->emailThrottleKey();
        $ipKey = $this->ipThrottleKey();

        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Trop de tentatives. Réessayez dans %d secondes.',
                    RateLimiter::availableIn($emailKey),
                ),
            ]);
        }

        if (RateLimiter::tooManyAttempts($ipKey, 50)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Trop de tentatives depuis cette IP. Réessayez dans %d secondes.',
                    RateLimiter::availableIn($ipKey),
                ),
            ]);
        }
    }

    private function emailThrottleKey(): string
    {
        return Str::transliterate(
            'login:email:'.Str::lower((string) $this->string('email')).'|'.$this->ip(),
        );
    }

    private function ipThrottleKey(): string
    {
        return 'login:ip:'.$this->ip();
    }
}

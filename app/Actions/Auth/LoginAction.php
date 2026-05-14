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
 * Orchestre une tentative de connexion :
 *   1. vérifie le rate-limit (lève {@see TooManyLoginAttemptsException})
 *   2. tente l'authentification (lève {@see InvalidCredentialsException}
 *      en cas d'échec, et incrémente le compteur de tentatives)
 *   3. au succès, reset les compteurs et trace `last_login_at`
 *
 * Action stateless - toute la mécanique session/cookie reste à la
 * charge du contrôleur (regenerate, redirect intended).
 *
 * Conforme ADR-0013 R3 : pas d'Eloquent ici, lectures/écritures
 * passent par les façades framework (Auth, Date) et le service
 * d'orchestration {@see LoginAttemptService}.
 */
final readonly class LoginAction
{
    public function __construct(
        private LoginAttemptService $attempts,
    ) {}

    /**
     * @throws TooManyLoginAttemptsException si le rate-limit est atteint
     * @throws InvalidCredentialsException si email/password invalides
     */
    public function execute(string $email, string $password, string $ip): User
    {
        $this->attempts->ensureNotRateLimited($email, $ip);

        $emailHash = hash('sha256', mb_strtolower($email));

        if (! Auth::attempt(['email' => $email, 'password' => $password], false)) {
            $this->attempts->recordFailedAttempt($email, $ip);

            // Log forensic · email haché pour corrélation sans fuiter
            // la PII si les fichiers de log sont compromis.
            // Cf. ADR-0011 § 3 + plan-remédiation Vague 1 Lot 1 D2 (F-10-002).
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

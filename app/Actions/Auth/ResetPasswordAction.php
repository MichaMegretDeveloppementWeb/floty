<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ResetPasswordData;
use App\Exceptions\Auth\InvalidResetTokenException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Réinitialise le mot de passe à partir d'un token valide.
 *
 * Délègue la mécanique de validation du token à `Password::reset()`
 * (qui hash et compare avec la table `password_reset_tokens` puis
 * supprime le token consommé). Le callback de update applique le hash
 * via `Hash::make()` (équivalent BCRYPT_ROUNDS env) + reset du
 * `remember_token` (invalide les cookies « se souvenir de moi »
 * éventuels) + déclenche l'event `PasswordReset` pour les listeners
 * downstream.
 *
 * Cf. plan-remédiation Vague 1 Lot 2 D4.3 (F-10-006) + ADR-0012 rev. 1.1.
 *
 * @throws InvalidResetTokenException si le token est invalide / expiré
 */
final readonly class ResetPasswordAction
{
    public function execute(ResetPasswordData $data, string $ip): void
    {
        $emailHash = hash('sha256', mb_strtolower($data->email));

        $status = Password::reset(
            [
                'email' => $data->email,
                'password' => $data->password,
                'password_confirmation' => $data->passwordConfirmation,
                'token' => $data->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            Log::channel('auth')->notice('password.reset_failed', [
                'email_hash' => $emailHash,
                'ip' => $ip,
                'status' => $status,
            ]);

            throw InvalidResetTokenException::make();
        }

        Log::channel('auth')->notice('password.reset_completed', [
            'email_hash' => $emailHash,
            'ip' => $ip,
        ]);
    }
}

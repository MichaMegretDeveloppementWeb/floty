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
 * Resets the password from a valid token. Delegates token validation
 * to `Password::reset()`; the callback rehashes the password, rotates
 * `remember_token` (invalidates any persistent cookies), and fires the
 * `PasswordReset` event for downstream listeners.
 *
 * @throws InvalidResetTokenException when the token is invalid or expired
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

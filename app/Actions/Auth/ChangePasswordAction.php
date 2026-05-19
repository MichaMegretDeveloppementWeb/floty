<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ChangePasswordData;
use App\Exceptions\Auth\CurrentPasswordMismatchException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Changes the password of an already-authenticated user. Verifies the
 * current password via Hash::check, updates the password and rotates
 * `remember_token`, then invalidates all other active sessions.
 *
 * @throws CurrentPasswordMismatchException when the current password is wrong
 */
final readonly class ChangePasswordAction
{
    public function execute(User $user, ChangePasswordData $data, string $ip): void
    {
        if (! Hash::check($data->currentPassword, $user->password)) {
            Log::channel('auth')->notice('password.change_failed', [
                'user_id' => $user->id,
                'email_hash' => hash('sha256', mb_strtolower($user->email)),
                'ip' => $ip,
                'reason' => 'current_password_mismatch',
            ]);

            throw CurrentPasswordMismatchException::make();
        }

        $user->forceFill([
            'password' => Hash::make($data->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Must be called after the password update; Laravel needs the
        // current hash to invalidate the other sessions.
        Auth::logoutOtherDevices($data->password);

        Log::channel('auth')->notice('password.changed', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', mb_strtolower($user->email)),
            'ip' => $ip,
        ]);
    }
}

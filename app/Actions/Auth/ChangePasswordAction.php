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
 * Change le mot de passe d'un utilisateur **déjà authentifié**.
 *
 * Vérifie le `currentPassword` via Hash::check (et NON via la couche
 * validation, pour ne pas exposer la mécanique de hash). Update ensuite
 * le password + reset du `remember_token` (invalide les cookies « se
 * souvenir de moi »).
 *
 * Sécurité supplémentaire · `Auth::logoutOtherDevices($newPassword)`
 * invalide toutes les **autres** sessions actives sur d'autres
 * navigateurs/appareils (utile si l'utilisateur change le password
 * suite à une suspicion de compromission). La session courante reste
 * active.
 *
 * Cf. plan-remédiation Vague 1 Lot 2 D4.4 (F-10-006) + ADR-0012 rev. 1.1.
 *
 * @throws CurrentPasswordMismatchException si le current password est faux
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

        // Invalide toutes les autres sessions actives (autres navigateurs).
        // Doit être appelé après l'update du password sinon Laravel ne
        // peut plus valider le hash courant.
        Auth::logoutOtherDevices($data->password);

        Log::channel('auth')->notice('password.changed', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', mb_strtolower($user->email)),
            'ip' => $ip,
        ]);
    }
}

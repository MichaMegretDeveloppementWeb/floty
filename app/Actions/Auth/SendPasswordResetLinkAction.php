<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Envoie un lien de réinitialisation de mot de passe à l'adresse fournie.
 *
 * **Anti email enumeration** · `Password::sendResetLink()` retourne un
 * statut interne qui distingue « email inconnu » d'autres erreurs, mais
 * cette Action **ne propage jamais** ce statut au consommateur · le
 * controller affiche toujours le même message générique. Sans ça, un
 * attaquant pourrait énumérer les emails enregistrés.
 *
 * Le canal `auth` reçoit la trace (email haché) pour audit forensic ·
 * cf. ADR-0011 § 3 + plan-remédiation Vague 1 Lot 2 D2 + D4.
 *
 * Conforme ADR-0013 R3 · pas d'Eloquent ici, lectures/écritures via
 * la façade `Password` (qui orchestre la table `password_reset_tokens`
 * + l'envoi de la Notification custom branchée sur le User model).
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

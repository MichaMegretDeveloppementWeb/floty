<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification de récupération de mot de passe Floty (ADR-0012 rev. 1.1).
 *
 * Override la `Illuminate\Auth\Notifications\ResetPassword` par défaut
 * pour envoyer un mail français custom (sujet, template Blade
 * `emails.password-reset`) plutôt que le markdown générique Laravel.
 *
 * Branchée via `User::sendPasswordResetNotification()` qui est appelée
 * par `Password::sendResetLink()` dans
 * {@see SendPasswordResetLinkAction}.
 *
 * Le token est éphémère (60 min, cf. `config/auth.php` passwords.users.expire)
 * et stocké hashé dans la table `password_reset_tokens`. La validité est
 * vérifiée par `Password::reset()` au moment du reset effectif.
 */
final class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe Floty')
            ->view('emails.password-reset', [
                'resetUrl' => $resetUrl,
                'firstName' => $notifiable->first_name ?? '',
                'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Floty password-reset notification (ADR-0012 rev. 1.1). Overrides
 * Laravel's default markdown notification with a French custom Blade
 * template (`emails.password-reset`).
 *
 * Wired through `User::sendPasswordResetNotification()`, invoked by
 * `Password::sendResetLink()` inside {@see SendPasswordResetLinkAction}.
 *
 * The token is short-lived (60 minutes, configured via
 * `auth.passwords.users.expire`) and stored hashed in the
 * `password_reset_tokens` table; `Password::reset()` validates it on
 * the actual reset.
 */
final class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * Notification channels for this notification (mail only).
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail message with the reset link.
     */
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

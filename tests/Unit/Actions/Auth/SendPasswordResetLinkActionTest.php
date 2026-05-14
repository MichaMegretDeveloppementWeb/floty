<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Models\User;
use App\Notifications\Auth\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit isolés sur l'orchestration `SendPasswordResetLinkAction`.
 *
 * Vérifie l'invariant clé · l'Action n'expose JAMAIS au consommateur le
 * statut interne de `Password::sendResetLink()` (anti email enumeration).
 */
final class SendPasswordResetLinkActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function envoie_la_notification_quand_le_user_existe(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'real@floty.test',
            'password' => Hash::make('whatever'),
        ]);

        $action = $this->app->make(SendPasswordResetLinkAction::class);
        $action->execute('real@floty.test', '127.0.0.1');

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    #[Test]
    public function ne_leve_pas_d_exception_pour_un_user_inexistant(): void
    {
        // Invariant anti email enumeration · l'Action ne doit jamais
        // signaler au consommateur que l'email est inconnu.
        Notification::fake();

        $action = $this->app->make(SendPasswordResetLinkAction::class);

        // Ne doit pas lever.
        $action->execute('ghost@floty.test', '127.0.0.1');

        Notification::assertNothingSent();
    }
}

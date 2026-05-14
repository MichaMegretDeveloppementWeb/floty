<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Data\Auth\ResetPasswordData;
use App\Exceptions\Auth\InvalidResetTokenException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit isolés sur l'orchestration `ResetPasswordAction` ·
 * vérifie la levée de l'exception sur token invalide et la mise à
 * jour effective du password sur token valide.
 */
final class ResetPasswordActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_le_password_quand_le_token_est_valide(): void
    {
        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $action = $this->app->make(ResetPasswordAction::class);
        $action->execute(
            new ResetPasswordData(
                token: $token,
                email: 'test@floty.test',
                password: 'fresh-password-99',
                passwordConfirmation: 'fresh-password-99',
            ),
            '127.0.0.1',
        );

        $user->refresh();
        self::assertTrue(Hash::check('fresh-password-99', $user->password));
    }

    #[Test]
    public function leve_invalid_reset_token_exception_si_token_inconnu(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('old-password'),
        ]);

        $action = $this->app->make(ResetPasswordAction::class);

        $this->expectException(InvalidResetTokenException::class);

        $action->execute(
            new ResetPasswordData(
                token: 'forged-token-not-in-db',
                email: 'test@floty.test',
                password: 'whatever-12345',
                passwordConfirmation: 'whatever-12345',
            ),
            '127.0.0.1',
        );
    }
}

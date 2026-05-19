<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Data\Auth\ChangePasswordData;
use App\Exceptions\Auth\CurrentPasswordMismatchException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChangePasswordActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_le_password_quand_le_current_est_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('the-current-pwd'),
        ]);

        Auth::login($user);

        $action = $this->app->make(ChangePasswordAction::class);
        $action->execute(
            $user,
            new ChangePasswordData(
                currentPassword: 'the-current-pwd',
                password: 'the-fresh-pwd-99',
                passwordConfirmation: 'the-fresh-pwd-99',
            ),
            '127.0.0.1',
        );

        $user->refresh();
        self::assertTrue(Hash::check('the-fresh-pwd-99', $user->password));
    }

    #[Test]
    public function leve_current_password_mismatch_si_current_password_faux(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-pwd'),
        ]);

        $action = $this->app->make(ChangePasswordAction::class);

        $this->expectException(CurrentPasswordMismatchException::class);

        $action->execute(
            $user,
            new ChangePasswordData(
                currentPassword: 'wrong-current',
                password: 'whatever-12345',
                passwordConfirmation: 'whatever-12345',
            ),
            '127.0.0.1',
        );

        $user->refresh();
        self::assertTrue(Hash::check('correct-pwd', $user->password));
    }
}

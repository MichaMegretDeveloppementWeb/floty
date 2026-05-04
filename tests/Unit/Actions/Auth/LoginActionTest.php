<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LoginAction;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TooManyLoginAttemptsException;
use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests isolés de l'orchestration auth - vérifie que l'Action :
 *   - délègue le rate-limit au service avant de tenter Auth::attempt
 *   - lève les exceptions typées attendues
 *   - met à jour `last_login_at` au succès et clear les compteurs.
 */
final class LoginActionTest extends TestCase
{
    use RefreshDatabase;

    private const string EMAIL = 'orchestration@floty.test';

    private const string IP = '198.51.100.42';

    private LoginAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        Request::swap(\Illuminate\Http\Request::create('/login'));
        $this->action = $this->app->make(LoginAction::class);

        RateLimiter::clear('login:email:'.self::EMAIL.'|'.self::IP);
        RateLimiter::clear('login:ip:'.self::IP);
    }

    #[Test]
    public function retourne_user_si_credentials_ok(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'password' => Hash::make('correct-password'),
        ]);

        $authenticated = $this->action->execute(self::EMAIL, 'correct-password', self::IP);

        $this->assertSame($user->id, $authenticated->id);
    }

    #[Test]
    public function throw_invalid_credentials_si_password_faux(): void
    {
        User::factory()->create([
            'email' => self::EMAIL,
            'password' => Hash::make('correct-password'),
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $this->action->execute(self::EMAIL, 'wrong-password', self::IP);
    }

    #[Test]
    public function throw_invalid_credentials_si_email_inconnu(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->action->execute('unknown@floty.test', 'whatever', self::IP);
    }

    #[Test]
    public function throw_too_many_attempts_si_rate_limit_email_atteint(): void
    {
        $service = $this->app->make(LoginAttemptService::class);

        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $service->recordFailedAttempt(self::EMAIL, self::IP);
        }

        $this->expectException(TooManyLoginAttemptsException::class);

        $this->action->execute(self::EMAIL, 'whatever', self::IP);
    }

    #[Test]
    public function met_a_jour_last_login_at_au_succes(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'password' => Hash::make('correct-password'),
            'last_login_at' => null,
        ]);

        $now = Date::parse('2026-04-28 09:30:00');
        Date::setTestNow($now);

        $this->action->execute(self::EMAIL, 'correct-password', self::IP);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame($now->toDateTimeString(), $user->last_login_at->toDateTimeString());
    }

    #[Test]
    public function clear_compteurs_au_succes(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'password' => Hash::make('correct-password'),
        ]);

        $service = $this->app->make(LoginAttemptService::class);

        // Pré-charge 4 tentatives ratées pour s'approcher du seuil.
        for ($i = 0; $i < 4; $i++) {
            $service->recordFailedAttempt(self::EMAIL, self::IP);
        }

        $this->action->execute(self::EMAIL, 'correct-password', self::IP);

        // Après succès, les compteurs sont vidés → on peut faire 5
        // nouvelles tentatives avant blocage.
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL - 1; $i++) {
            $service->recordFailedAttempt(self::EMAIL, self::IP);
        }

        // Pas d'exception → preuve que le compteur a bien été reset.
        $service->ensureNotRateLimited(self::EMAIL, self::IP);

        $this->expectNotToPerformAssertions();
    }
}

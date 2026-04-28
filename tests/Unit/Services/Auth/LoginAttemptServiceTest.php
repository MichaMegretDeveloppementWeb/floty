<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Exceptions\Auth\TooManyLoginAttemptsException;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests isolés du rate-limit, sans monter de requête HTTP.
 */
final class LoginAttemptServiceTest extends TestCase
{
    private const string EMAIL = 'audit@floty.test';

    private const string IP = '203.0.113.7';

    private LoginAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Le service appelle `request()` pour produire l'événement
        // Lockout — on lui fournit une requête vide à défaut de
        // contexte HTTP réel.
        Request::swap(\Illuminate\Http\Request::create('/login'));

        $this->service = $this->app->make(LoginAttemptService::class);

        RateLimiter::clear($this->emailKey());
        RateLimiter::clear($this->ipKey());
    }

    #[Test]
    public function passe_si_aucune_tentative(): void
    {
        $this->service->ensureNotRateLimited(self::EMAIL, self::IP);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_apres_5_tentatives_email(): void
    {
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->service->recordFailedAttempt(self::EMAIL, self::IP);
        }

        try {
            $this->service->ensureNotRateLimited(self::EMAIL, self::IP);
            $this->fail('TooManyLoginAttemptsException attendue.');
        } catch (TooManyLoginAttemptsException $e) {
            $this->assertSame(TooManyLoginAttemptsException::SCOPE_EMAIL, $e->scope);
            $this->assertGreaterThan(0, $e->retryAfterSeconds);
            $this->assertStringStartsWith('Trop de tentatives. Réessayez dans', $e->getUserMessage());
        }
    }

    #[Test]
    public function throw_apres_50_tentatives_ip(): void
    {
        // On utilise des emails différents pour ne pas saturer le
        // compteur email+IP (qui se déclencherait à 5 tentatives).
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_IP; $i++) {
            $this->service->recordFailedAttempt("user{$i}@floty.test", self::IP);
        }

        try {
            $this->service->ensureNotRateLimited('autre@floty.test', self::IP);
            $this->fail('TooManyLoginAttemptsException attendue.');
        } catch (TooManyLoginAttemptsException $e) {
            $this->assertSame(TooManyLoginAttemptsException::SCOPE_IP, $e->scope);
            $this->assertGreaterThan(0, $e->retryAfterSeconds);
            $this->assertStringStartsWith('Trop de tentatives depuis cette IP', $e->getUserMessage());
        }
    }

    #[Test]
    public function clear_reset_compteurs(): void
    {
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->service->recordFailedAttempt(self::EMAIL, self::IP);
        }

        $this->service->clearAttempts(self::EMAIL, self::IP);

        // Aucune exception après clear même au seuil de blocage.
        $this->service->ensureNotRateLimited(self::EMAIL, self::IP);

        $this->expectNotToPerformAssertions();
    }

    private function emailKey(): string
    {
        return 'login:email:'.self::EMAIL.'|'.self::IP;
    }

    private function ipKey(): string
    {
        return 'login:ip:'.self::IP;
    }
}

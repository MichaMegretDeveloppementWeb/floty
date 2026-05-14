<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le flow forgot-password bout en bout · affichage formulaire,
 * envoi de l'email custom, anti email enumeration, throttle 3/15 min,
 * audit log canal `auth`.
 *
 * Cf. plan-remédiation Vague 1 Lot 2 D4.2 (F-10-006) + ADR-0012 rev. 1.1.
 */
final class ForgotPasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset throttle global · le RateLimiter persiste entre tests.
        RateLimiter::clear('login:ip:127.0.0.1');
    }

    #[Test]
    public function la_page_forgot_password_repond_200_pour_un_invite(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword/Index'));
    }

    #[Test]
    public function un_user_existant_recoit_la_notification_password_reset_custom(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('current-password'),
        ]);

        $this->post('/forgot-password', ['email' => 'test@floty.test'])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    #[Test]
    public function un_email_inconnu_ne_leak_pas_son_inexistence_meme_message_succes(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unknown@floty.test'])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        Notification::assertNothingSent();
    }

    #[Test]
    public function un_email_invalide_renvoie_une_erreur_de_validation_francaise(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'pas-un-email'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => 'Le format de l\'adresse e-mail est invalide.']);
    }

    #[Test]
    public function le_throttle_3_par_15min_bloque_au_dela_de_3_demandes(): void
    {
        // F-10-006 D4.2 · throttle 3/15min sur POST /forgot-password ·
        // anti-spam mail (un envoi = coût SMTP réel).
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'test@floty.test'])
                ->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'test@floty.test'])
            ->assertStatus(429);
    }

    #[Test]
    public function la_route_forgot_password_porte_le_middleware_throttle(): void
    {
        // Anti-régression · le middleware `throttle:3,15` doit rester
        // posé sur POST /forgot-password.
        $route = Route::getRoutes()->getByName('password.email');

        $this->assertNotNull($route, 'La route `password.email` doit exister.');
        $this->assertContains(
            'throttle:3,15',
            $route->gatherMiddleware(),
            'POST /forgot-password doit porter `throttle:3,15` (anti-spam mail).',
        );
    }

    #[Test]
    public function le_canal_auth_logge_password_reset_requested_avec_email_hashe(): void
    {
        // Cohérent avec D2 · le flow d'audit utilise canal `auth` notice.
        // Pattern file-based · vérification que l'event atterrit dans
        // storage/logs/auth-YYYY-MM-DD.log.
        $logFile = storage_path('logs/auth-'.now()->format('Y-m-d').'.log');
        @unlink($logFile);

        // Désactiver throttle pour pouvoir asserter le log isolément.
        $this->withoutMiddleware(ThrottleRequests::class);

        Notification::fake();

        $this->post('/forgot-password', ['email' => 'test@floty.test']);

        self::assertFileExists($logFile);
        $content = (string) file_get_contents($logFile);

        self::assertStringContainsString('password.reset_requested', $content);
        self::assertStringContainsString(hash('sha256', 'test@floty.test'), $content);
        // Anti-PII · l'email en clair ne doit pas apparaître.
        self::assertStringNotContainsString('"email":"test@floty.test"', $content);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le flow de connexion bout en bout : login OK / KO,
 * rate-limit double couche (email+IP / IP seule), logout, et trace
 * `last_login_at`.
 *
 * Complète {@see UserRoutesAuthTest} qui ne fait que vérifier la
 * protection middleware sur les routes user.*.
 */
final class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset systématique des compteurs RateLimiter pour éviter
        // les fuites d'état entre tests (le RateLimiter est global).
        RateLimiter::clear('login:email:test@floty.test|127.0.0.1');
        RateLimiter::clear('login:ip:127.0.0.1');
    }

    #[Test]
    public function login_ok_cree_session_redirige_dashboard_et_met_a_jour_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
            'last_login_at' => null,
        ]);

        $now = Date::parse('2026-04-28 12:00:00');
        Date::setTestNow($now);

        $response = $this->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame($now->toDateTimeString(), $user->last_login_at->toDateTimeString());
    }

    #[Test]
    public function login_ko_renvoie_erreur_validation_sur_email_et_n_authentifie_pas(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
        $this->assertGuest();
    }

    #[Test]
    public function login_ko_email_inconnu_donne_meme_message_qu_un_password_faux(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'unknown@floty.test',
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
        $this->assertGuest();
    }

    #[Test]
    public function rate_limit_email_apres_5_tentatives_bloque_avec_message_attente(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
        ]);

        Event::fake([Lockout::class]);

        // 5 tentatives ratées → 5e enregistre toujours l'attempt.
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->post('/login', [
                'email' => 'test@floty.test',
                'password' => 'wrong-password',
            ]);
        }

        // 6e tentative → blocage avant même la vérif de password.
        $response = $this->from('/login')->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertInvalid(['email' => 'Trop de tentatives. Réessayez dans']);
        $this->assertGuest();

        Event::assertDispatched(Lockout::class);
    }

    #[Test]
    public function rate_limit_ip_apres_10_tentatives_bloque_avec_message_attente(): void
    {
        // Chaque tentative utilise un email différent → seul le compteur IP
        // s'incrémente. On désactive le middleware throttle pour exercer la
        // couche applicative en isolation et déclencher le message FR de
        // `LoginAttemptService` au lieu d'un 429 brut.
        $this->withoutMiddleware(ThrottleRequests::class);

        Event::fake([Lockout::class]);

        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_IP; $i++) {
            $this->post('/login', [
                'email' => "user{$i}@floty.test",
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'autre@floty.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertInvalid(['email' => 'Trop de tentatives depuis cette IP']);
        $this->assertGuest();

        Event::assertDispatched(Lockout::class);
    }

    #[Test]
    public function logout_invalide_session_et_redirige_vers_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(Auth::user());
    }

    #[Test]
    public function un_user_deja_connecte_est_redirige_par_le_middleware_guest(): void
    {
        $user = User::factory()->create();

        // GET /login derrière le middleware `guest` → redirect home (par
        // défaut Laravel `RedirectIfAuthenticated`). Pas un dashboard,
        // c'est un comportement Laravel standard non surchargé en V1.
        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/');
    }

    #[Test]
    public function flow_auth_logge_success_failed_lockout_sur_canal_auth_sans_pii(): void
    {
        // Test combiné · vérifie qu'au canal `auth` on logge bien les 3
        // types d'événements (success/failed/lockout) avec email haché,
        // et que ni l'email en clair ni le password ne fuient dans le log.
        $logFile = storage_path('logs/auth-'.Carbon::now()->format('Y-m-d').'.log');
        @unlink($logFile);

        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
        ]);

        // 1 login OK
        $this->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ])->assertRedirect('/app/dashboard');
        $this->post('/logout');

        // Reset rate-limit · on veut isoler la séquence Lockout suivante.
        RateLimiter::clear('login:email:test@floty.test|127.0.0.1');
        RateLimiter::clear('login:ip:127.0.0.1');

        // 5 failed → 6e déclenche Lockout
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->post('/login', [
                'email' => 'test@floty.test',
                'password' => 'wrong-password',
            ]);
        }
        $this->from('/login')->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ])->assertRedirect('/login');

        $this->assertFileExists($logFile, 'Le fichier de log auth doit être créé après les actions.');
        $content = (string) file_get_contents($logFile);

        // Présence des 3 types d'événements
        $this->assertStringContainsString('login.success', $content);
        $this->assertStringContainsString('login.failed', $content);
        $this->assertStringContainsString('login.lockout', $content);

        // Hash email pour corrélation forensique
        $emailHash = hash('sha256', 'test@floty.test');
        $this->assertStringContainsString($emailHash, $content);

        // PII safety · email en clair et passwords NE doivent PAS apparaître.
        // Note · on cherche `test@floty.test` entouré de délimiteurs JSON pour
        // éviter les faux-positifs sur le hash hex (qui contient parfois ces
        // chars · improbable mais on assertString sur le brut, robuste).
        $this->assertStringNotContainsString('"email":"test@floty.test"', $content);
        $this->assertStringNotContainsString('correct-password', $content);
        $this->assertStringNotContainsString('wrong-password', $content);
    }

    #[Test]
    public function la_route_login_store_porte_le_middleware_throttle(): void
    {
        // Anti-régression · le middleware `throttle:10,2` doit rester posé sur
        // la route POST /login (double couche anti brute-force avec le service
        // LoginAttemptService applicatif). Cf. ADR-0011 § 3.
        $route = Route::getRoutes()->getByName('login.store');

        $this->assertNotNull($route, 'La route `login.store` doit exister.');
        $this->assertContains(
            'throttle:10,2',
            $route->gatherMiddleware(),
            'La route POST /login doit porter le middleware `throttle:10,2` (anti brute-force IP).',
        );
    }

    #[Test]
    public function login_rejette_email_de_plus_de_255_caracteres(): void
    {
        // Borne `max:255` empêche un soft-DoS via input gigantesque sur la clé
        // RateLimiter (cf. LoginAttemptService::emailKey). RFC 5321 limite à 254.
        $tooLong = str_repeat('a', 250).'@x.com'; // 256 chars > 255

        $this->post('/login', [
            'email' => $tooLong,
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_rejette_password_de_plus_de_255_caracteres(): void
    {
        // Borne `max:255` empêche un soft-DoS via input gigantesque sur le hash
        // bcrypt (qui tronque silencieusement à 72 bytes après l'overhead).
        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => str_repeat('x', 256),
        ])->assertSessionHasErrors('password');
    }
}

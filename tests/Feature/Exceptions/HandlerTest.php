<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Exceptions\Fiscal\FiscalCalculationException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie le comportement du handler `bootstrap/app.php` pour les exceptions métier
 * sur requêtes JSON et visites HTML/Inertia.
 */
final class HandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get(
            '/_test/throw-fiscal',
            static fn () => throw FiscalCalculationException::yearNotSupported(2099),
        );
    }

    #[Test]
    public function exception_metier_sur_requete_json_renvoie_422_structure(): void
    {
        $this->getJson('/_test/throw-fiscal')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonPath('code', 'FiscalCalculationException');
    }

    #[Test]
    public function exception_metier_sur_requete_json_renvoie_le_message_utilisateur_francais(): void
    {
        $response = $this->getJson('/_test/throw-fiscal');

        $message = $response->json('message');
        self::assertIsString($message);
        self::assertStringContainsString("n'est pas supportée", $message);
        self::assertStringNotContainsString('not supported by', $message);
    }

    #[Test]
    public function exception_metier_sur_visite_html_redirige_avec_flash_toast_error(): void
    {
        $this->from('/login')
            ->get('/_test/throw-fiscal')
            ->assertRedirect('/login')
            ->assertSessionHas('toast-error');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Console\Commands\FiscalAuditLinksCommand;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la commande artisan `fiscal:audit-links` (AUDIT-E · audit
 * fiscal renforcé 14/05/2026) · vérifie l'invariant fonctionnel sans
 * dépendre de la disponibilité réseau (mode `--no-http`).
 *
 * Garde-fou · si une règle est ajoutée sans `consulted_at` ou sans
 * URL, la commande la flague. Si toutes les entrées sont saines, exit
 * code 0.
 */
final class FiscalAuditLinksCommandTest extends TestCase
{
    #[Test]
    public function commande_existe_et_repond_a_help(): void
    {
        $exit = Artisan::call('fiscal:audit-links', ['--help' => true]);
        self::assertSame(0, $exit);
    }

    #[Test]
    public function mode_no_http_retourne_zero_quand_toutes_les_regles_ont_consulted_at_recent(): void
    {
        $exit = Artisan::call('fiscal:audit-links', [
            '--no-http' => true,
            '--max-age' => 365,
        ]);

        self::assertSame(0, $exit);
        $output = Artisan::output();
        self::assertStringContainsString('entrées legalBasis recensées', $output);
        self::assertStringContainsString('Aucune anomalie détectée', $output);
    }

    #[Test]
    public function mode_strict_avec_max_age_zero_detecte_des_anomalies(): void
    {
        // max-age=-1 force tout consulted_at à apparaître comme « trop ancien »
        // (today.diffInDays(today) = 0 > -1) · vérifie le comportement strict
        // sans casser le jeu de données réel.
        $exit = Artisan::call('fiscal:audit-links', [
            '--no-http' => true,
            '--max-age' => -1,
            '--strict' => true,
        ]);

        self::assertSame(FiscalAuditLinksCommand::FAILURE, $exit);
        $output = Artisan::output();
        self::assertStringContainsString('Anomalies détectées', $output);
        self::assertStringContainsString('consulted_at trop ancien', $output);
    }
}

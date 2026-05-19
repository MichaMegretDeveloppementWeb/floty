<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anti-régression sur les canaux thématiques de logging.
 * Garantit que les 10 canaux thématiques Floty restent configurés avec les
 * rétentions et levels attendus (cf. implementation-rules/gestion-erreurs.md).
 */
final class ChannelsConfigurationTest extends TestCase
{
    /**
     * @return list<array{string, int, string}>
     */
    public static function canauxThematiques(): array
    {
        // Tuples · [nom canal, rétention min en jours, level par défaut].
        return [
            ['auth', 30, 'notice'],
            ['fiscal', 90, 'notice'],
            ['declarations', 365, 'notice'],
            ['companies', 30, 'notice'],
            ['vehicles', 30, 'notice'],
            ['drivers', 30, 'notice'],
            ['contracts', 90, 'notice'],
            ['unavailabilities', 30, 'notice'],
            ['invoices', 365, 'notice'],
            ['pdf', 30, 'notice'],
        ];
    }

    #[Test]
    public function chaque_canal_thematique_est_un_daily_avec_retention_attendue(): void
    {
        foreach (self::canauxThematiques() as [$channel, $minDays, $level]) {
            $config = config("logging.channels.{$channel}");

            $this->assertIsArray($config, "Le canal `{$channel}` doit être configuré.");
            $this->assertSame('daily', $config['driver'], "Canal `{$channel}` driver attendu `daily`.");
            $this->assertGreaterThanOrEqual(
                $minDays,
                (int) $config['days'],
                "Canal `{$channel}` rétention attendue >= {$minDays} j (trouvé {$config['days']} j).",
            );
            $this->assertSame(
                $level,
                $config['level'],
                "Canal `{$channel}` level attendu `{$level}` (trouvé `{$config['level']}`).",
            );
        }
    }

    #[Test]
    public function le_canal_cache_a_un_level_warning_pour_eviter_le_volume_debug(): void
    {
        $config = config('logging.channels.cache');

        $this->assertIsArray($config, 'Le canal `cache` doit être configuré.');
        $this->assertSame('daily', $config['driver']);
        $this->assertSame('warning', $config['level']);
    }

    #[Test]
    public function le_canal_assignments_est_supprime_apres_drop_table_assignments(): void
    {
        // La table `assignments` a été supprimée · le canal dédié n'a plus d'utilité
        // et doit être retiré pour éviter qu'un futur dev croie qu'il est branché.
        $this->assertNull(
            config('logging.channels.assignments'),
            'Le canal orphelin `assignments` doit être supprimé.',
        );
    }
}

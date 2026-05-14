<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Date;

use App\Support\Date\YearBoundsCache;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 3 D6 (F-16-009).
 */
final class YearBoundsCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        YearBoundsCache::flush();
    }

    #[Test]
    public function start_retourne_le_1er_janvier_a_minuit_pour_l_annee_demandee(): void
    {
        $start = YearBoundsCache::start(2026);

        self::assertSame('2026-01-01 00:00:00', $start->format('Y-m-d H:i:s'));
        self::assertInstanceOf(CarbonImmutable::class, $start);
    }

    #[Test]
    public function end_retourne_le_31_decembre_a_23h59m59_pour_l_annee_demandee(): void
    {
        $end = YearBoundsCache::end(2026);

        self::assertSame('2026-12-31 23:59:59', $end->format('Y-m-d H:i:s'));
        self::assertInstanceOf(CarbonImmutable::class, $end);
    }

    #[Test]
    public function start_renvoie_la_meme_instance_pour_des_appels_repetes_meme_annee(): void
    {
        // Identité === (pas seulement ==) · l'objectif du cache est
        // d'éviter de re-construire un CarbonImmutable à chaque appel.
        $a = YearBoundsCache::start(2026);
        $b = YearBoundsCache::start(2026);

        self::assertSame($a, $b);
    }

    #[Test]
    public function end_renvoie_la_meme_instance_pour_des_appels_repetes_meme_annee(): void
    {
        $a = YearBoundsCache::end(2026);
        $b = YearBoundsCache::end(2026);

        self::assertSame($a, $b);
    }

    #[Test]
    public function start_renvoie_des_instances_distinctes_pour_des_annees_differentes(): void
    {
        $a = YearBoundsCache::start(2025);
        $b = YearBoundsCache::start(2026);

        self::assertNotSame($a, $b);
        self::assertSame(2025, $a->year);
        self::assertSame(2026, $b->year);
    }

    #[Test]
    public function flush_vide_le_cache_et_force_de_nouvelles_instances(): void
    {
        $before = YearBoundsCache::start(2026);
        YearBoundsCache::flush();
        $after = YearBoundsCache::start(2026);

        // Même valeur sémantique mais nouvelle instance après flush.
        self::assertEquals($before->format('Y-m-d H:i:s'), $after->format('Y-m-d H:i:s'));
        self::assertNotSame($before, $after);
    }
}

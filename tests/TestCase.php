<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * TestCase de base Floty · étend `Illuminate\Foundation\Testing\TestCase`
 * pour bénéficier du bootstrap Laravel (container, facades, helpers).
 *
 * **Convention héritage** ·
 * - Tous les tests Feature + tests Unit nécessitant un cycle DB / le
 *   container Laravel héritent de cette classe.
 * - Les tests Unit purs (algorithmes, helpers stateless · ex.
 *   `OptimalRateBreakdownTest`) héritent directement de
 *   `PHPUnit\Framework\TestCase` pour éviter le coût de bootstrap inutile.
 *
 * Helpers communs à mutualiser ici si pattern récurrent émerge.
 * Ne pas y mettre de logique métier · réservé à l'infrastructure de test.
 */
abstract class TestCase extends BaseTestCase {}

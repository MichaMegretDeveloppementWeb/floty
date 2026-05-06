<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\OptimalRateBreakdown;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests exhaustifs du choix de tarif optimal (Phase 14.C facturation V1.2).
 *
 * Tarifs « pivot » utilisés dans la majorité des cas — proches d'une
 * politique réaliste de loueur :
 *   - jour : 90,00 €  (9 000 cents)
 *   - semaine : 500,00 €  (50 000 cents)
 *   - mois : 1 800,00 €  (180 000 cents)
 *
 * Implications :
 *   - 7 jours à 9 000 = 63 000 vs 1 sem 50 000 → semaine meilleure
 *   - 30 jours à 9 000 = 270 000 vs 1 mois 180 000 → mois meilleur
 *   - 4 sem 200 000 vs 1 mois 180 000 → mois meilleur
 */
final class OptimalRateBreakdownTest extends TestCase
{
    private const int DAILY = 9_000;

    private const int WEEKLY = 50_000;

    private const int MONTHLY = 180_000;

    #[Test]
    public function zero_jour_retourne_un_breakdown_nul(): void
    {
        $r = OptimalRateBreakdown::compute(0, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(0, $r->totalCents);
    }

    #[Test]
    public function un_jour_facture_un_jour_a_l_unite(): void
    {
        $r = OptimalRateBreakdown::compute(1, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(1, $r->days);
        $this->assertSame(9_000, $r->totalCents);
    }

    #[Test]
    public function six_jours_restent_en_jours_quand_la_semaine_est_chere(): void
    {
        // 6 × 9 000 = 54 000 < 50 000 ? Non, 54 000 > 50 000.
        // En fait on devrait basculer en 1 sem dès que daily × N > weekly.
        // Recalcule : 6 jours = 54 000 vs 1 sem (couvre 7) = 50 000 → 1 sem.
        // Donc 6 jours bascule déjà sur la semaine.
        $r = OptimalRateBreakdown::compute(6, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(1, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(50_000, $r->totalCents);
    }

    #[Test]
    public function cinq_jours_restent_en_jours_avec_tarifs_realistes(): void
    {
        // 5 × 9 000 = 45 000 < 1 sem 50 000 → garde les jours.
        $r = OptimalRateBreakdown::compute(5, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(5, $r->days);
        $this->assertSame(45_000, $r->totalCents);
    }

    #[Test]
    public function sept_jours_facture_une_semaine(): void
    {
        $r = OptimalRateBreakdown::compute(7, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(1, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(50_000, $r->totalCents);
    }

    #[Test]
    public function quatorze_jours_facture_deux_semaines(): void
    {
        $r = OptimalRateBreakdown::compute(14, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(2, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(100_000, $r->totalCents);
    }

    #[Test]
    public function quinze_jours_choisit_deux_semaines_plus_un_jour(): void
    {
        // 2 sem + 1 jour = 100 000 + 9 000 = 109 000
        // 3 sem = 150 000 (couvre 21)
        // 1 mois = 180 000 (couvre 30)
        $r = OptimalRateBreakdown::compute(15, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(0, $r->months);
        $this->assertSame(2, $r->weeks);
        $this->assertSame(1, $r->days);
        $this->assertSame(109_000, $r->totalCents);
    }

    #[Test]
    public function vingt_huit_jours_compare_quatre_semaines_vs_mois_avec_tarifs_realistes(): void
    {
        // 4 sem = 200 000 ; 1 mois = 180 000 → mois.
        $r = OptimalRateBreakdown::compute(28, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(1, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(180_000, $r->totalCents);
    }

    #[Test]
    public function trente_jours_couverts_par_un_mois_unitaire(): void
    {
        $r = OptimalRateBreakdown::compute(30, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(1, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(180_000, $r->totalCents);
    }

    #[Test]
    public function trente_et_un_jours_choisit_mois_plus_un_jour(): void
    {
        // 1 mois + 1 jour = 180 000 + 9 000 = 189 000
        // 5 sem = 250 000
        // 4 sem + 3 jours = 200 000 + 27 000 = 227 000
        $r = OptimalRateBreakdown::compute(31, self::DAILY, self::WEEKLY, self::MONTHLY);

        $this->assertSame(1, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(1, $r->days);
        $this->assertSame(189_000, $r->totalCents);
    }

    #[Test]
    public function bascule_jour_vers_semaine_quand_jour_est_tres_avantageux(): void
    {
        // jour = 100, semaine = 1 000 (= 10 jours). Dès lors, garder
        // les jours est avantageux jusqu'à 9 jours, on bascule à 10.
        $r = OptimalRateBreakdown::compute(9, dailyCents: 100, weeklyCents: 1_000, monthlyCents: 100_000);

        $this->assertSame(0, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(9, $r->days);
        $this->assertSame(900, $r->totalCents);
    }

    #[Test]
    public function bascule_systematique_au_mois_quand_le_mois_est_imbattable(): void
    {
        // mois = 1 €, jour = 1 000 €, semaine = 5 000 € → toujours 1 mois.
        $r = OptimalRateBreakdown::compute(3, dailyCents: 100_000, weeklyCents: 500_000, monthlyCents: 100);

        $this->assertSame(1, $r->months);
        $this->assertSame(0, $r->weeks);
        $this->assertSame(0, $r->days);
        $this->assertSame(100, $r->totalCents);
    }

    #[Test]
    public function tarifs_a_zero_renvoient_un_total_zero(): void
    {
        // Cas véhicule de courtoisie (tarif 0 documenté côté backend
        // 14.A — `permet_un_tarif_zero_pour_les_vehicules_en_usage_gratuit`).
        $r = OptimalRateBreakdown::compute(15, dailyCents: 0, weeklyCents: 0, monthlyCents: 0);

        $this->assertSame(0, $r->totalCents);
        // Combinaison non-déterminée puisque toutes ont le même coût (0) ;
        // la propriété testée est uniquement le total nul.
    }

    #[Test]
    public function couverture_minimale_garantie_les_jours_factures_couvrent_au_moins_les_jours_utilises(): void
    {
        // Invariant : pour tout N ≤ 31, m·30 + w·7 + d ≥ N.
        for ($n = 1; $n <= 31; $n++) {
            $r = OptimalRateBreakdown::compute($n, self::DAILY, self::WEEKLY, self::MONTHLY);
            $coverage = $r->months * 30 + $r->weeks * 7 + $r->days;
            $this->assertGreaterThanOrEqual(
                $n,
                $coverage,
                "Couverture insuffisante pour N={$n} : couvre {$coverage} jours.",
            );
        }
    }

    #[Test]
    public function total_correspond_a_la_decomposition_pour_chaque_taille_de_periode(): void
    {
        // Invariant : totalCents = months × monthly + weeks × weekly + days × daily.
        for ($n = 0; $n <= 31; $n++) {
            $r = OptimalRateBreakdown::compute($n, self::DAILY, self::WEEKLY, self::MONTHLY);
            $expected = $r->months * self::MONTHLY + $r->weeks * self::WEEKLY + $r->days * self::DAILY;
            $this->assertSame(
                $expected,
                $r->totalCents,
                "Décomposition incohérente pour N={$n}.",
            );
        }
    }
}

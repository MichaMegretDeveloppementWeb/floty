<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Vehicle;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Couvre la cascade {@see PollutantCategory::derive()} (CIBS art. L. 421-134),
 * source unique de vérité côté backend (Repository à l'écriture +
 * R-2024-013 au calcul).
 */
final class PollutantCategoryDeriveTest extends TestCase
{
    #[Test]
    public function electrique_donne_categorie_e(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Electric,
            EuroStandard::Euro6dIscFcm,
            null,
        );

        self::assertSame(PollutantCategory::E, $category);
    }

    #[Test]
    public function hydrogene_donne_categorie_e(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Hydrogen,
            null,
            null,
        );

        self::assertSame(PollutantCategory::E, $category);
    }

    #[Test]
    public function combinaison_electrique_hydrogene_donne_categorie_e(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::ElectricHydrogen,
            null,
            UnderlyingCombustionEngineType::NotApplicable,
        );

        self::assertSame(PollutantCategory::E, $category);
    }

    #[Test]
    public function essence_euro_5_ou_plus_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Gasoline,
            EuroStandard::Euro5,
            null,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function gpl_euro_6d_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Lpg,
            EuroStandard::Euro6d,
            null,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function gnv_euro_5b_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Cng,
            EuroStandard::Euro5b,
            null,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function e85_euro_6_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::E85,
            EuroStandard::Euro6,
            null,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function essence_pre_euro_5_donne_categorie_les_plus_polluants(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Gasoline,
            EuroStandard::Euro4,
            null,
        );

        self::assertSame(PollutantCategory::MostPolluting, $category);
    }

    #[Test]
    public function diesel_quelle_que_soit_la_norme_donne_categorie_les_plus_polluants(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Diesel,
            EuroStandard::Euro6dIscFcm,
            null,
        );

        self::assertSame(PollutantCategory::MostPolluting, $category);
    }

    #[Test]
    public function hybride_a_sous_jacent_essence_euro_6_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::NonPluginHybrid,
            EuroStandard::Euro6,
            UnderlyingCombustionEngineType::Gasoline,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function hybride_rechargeable_a_sous_jacent_essence_euro_5_donne_categorie_1(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::PluginHybrid,
            EuroStandard::Euro5,
            UnderlyingCombustionEngineType::Gasoline,
        );

        self::assertSame(PollutantCategory::Category1, $category);
    }

    #[Test]
    public function hybride_a_sous_jacent_diesel_donne_categorie_les_plus_polluants(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::NonPluginHybrid,
            EuroStandard::Euro6,
            UnderlyingCombustionEngineType::Diesel,
        );

        self::assertSame(PollutantCategory::MostPolluting, $category);
    }

    #[Test]
    public function hybride_sans_sous_jacent_renseigne_donne_categorie_les_plus_polluants(): void
    {
        // Defensive default : sans information sur le moteur thermique,
        // on ne peut pas attribuer la Catégorie 1 - sécuritaire pour le
        // contribuable comme pour le fisc.
        $category = PollutantCategory::derive(
            EnergySource::NonPluginHybrid,
            EuroStandard::Euro6,
            null,
        );

        self::assertSame(PollutantCategory::MostPolluting, $category);
    }

    #[Test]
    public function essence_sans_norme_euro_donne_categorie_les_plus_polluants(): void
    {
        $category = PollutantCategory::derive(
            EnergySource::Gasoline,
            null,
            null,
        );

        self::assertSame(PollutantCategory::MostPolluting, $category);
    }
}

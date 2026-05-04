import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed } from 'vue';
import { toValue } from 'vue';

type EnergySource = App.Enums.Vehicle.EnergySource;
type EuroStandard = App.Enums.Vehicle.EuroStandard;
type UnderlyingCombustionEngineType = App.Enums.Vehicle.UnderlyingCombustionEngineType;
type PollutantCategory = App.Enums.Vehicle.PollutantCategory;

/**
 * Vignette Crit'Air - saisie optionnelle sur le formulaire véhicule
 * (rubrique non encore en BDD à date 2026-04-27 ; le composable est
 * prêt à être branché quand la colonne sera ajoutée à
 * `vehicle_fiscal_characteristics`).
 */
export type CritAirVignette = 'E' | '1' | '2' | '3' | '4' | '5' | 'unclassified';

export type CritAirCheckInput = {
    energySource: EnergySource | null;
    euroStandard: EuroStandard | null;
    underlyingCombustionEngineType: UnderlyingCombustionEngineType | null;
    critAirVignette: CritAirVignette | null;
};

export type CritAirCheckResult = {
    /** Catégorie polluants déduite par R-2024-013 (peut être null si données insuffisantes). */
    inferredPollutantCategory: PollutantCategory | null;
    /** Catégorie polluants attendue selon la vignette Crit'Air saisie. */
    expectedFromCritAir: PollutantCategory | null;
    /** Vrai si une divergence est détectée - alerte UI non bloquante. */
    hasMismatch: boolean;
    /** Message FR à afficher en banner. `null` si aucune alerte. */
    message: string | null;
};

/**
 * R-2024-024 - Garde-fou Crit'Air (CIBS BOFiP § 270).
 *
 * Compare la **catégorie polluants déduite** par R-2024-013
 * (motorisation + Euro) à la **catégorie attendue** par la vignette
 * Crit'Air saisie. En cas de divergence, retourne un message
 * d'alerte à afficher comme banner non bloquant sous la section
 * « Caractéristiques fiscales » du formulaire véhicule.
 *
 * **Non bloquant** : ne désactive jamais la soumission du formulaire.
 * L'utilisateur peut sauvegarder malgré l'alerte (la donnée saisie
 * fait foi). C'est un diagnostic de cohérence.
 *
 * Implémentation TS qui **réplique** la cascade R-2024-013 du backend
 * pour permettre la validation temps réel sans aller-retour HTTP.
 * Les deux implémentations doivent rester synchronisées - toute
 * modification de R-013 côté PHP impose un audit ici.
 */
export function useCritAirCheck(
    input: MaybeRefOrGetter<CritAirCheckInput>,
): ComputedRef<CritAirCheckResult> {
    return computed(() => {
        const data = toValue(input);
        const inferred = inferPollutantCategory(data);
        const expected = expectedCategoryFromCritAir(data.critAirVignette);

        if (inferred === null || expected === null || inferred === expected) {
            return {
                inferredPollutantCategory: inferred,
                expectedFromCritAir: expected,
                hasMismatch: false,
                message: null,
            };
        }

        return {
            inferredPollutantCategory: inferred,
            expectedFromCritAir: expected,
            hasMismatch: true,
            message: `Incohérence Crit'Air détectée : la motorisation déduit la catégorie « ${labelOf(inferred)} », mais la vignette Crit'Air saisie correspond à « ${labelOf(expected)} ». Vérifiez la saisie ou contactez le support si la vignette est correcte.`,
        };
    });
}

/**
 * Cascade R-2024-013 (cf. App\Fiscal\Year2024\Classification\
 * R2024_013_PollutantCategoryAssignment).
 */
function inferPollutantCategory(input: CritAirCheckInput): PollutantCategory | null {
    if (input.energySource === null) {
        return null;
    }

    if (
        input.energySource === 'electric'
        || input.energySource === 'hydrogen'
        || input.energySource === 'electric_hydrogen'
    ) {
        return 'e';
    }

    if (input.euroStandard === null) {
        return null;
    }

    if (!isEuro5OrAbove(input.euroStandard)) {
        return 'most_polluting';
    }

    const isPositiveIgnition = ['gasoline', 'lpg', 'cng', 'e85'].includes(input.energySource);
    const isHybridGasoline =
        ['plugin_hybrid', 'non_plugin_hybrid'].includes(input.energySource)
        && input.underlyingCombustionEngineType === 'gasoline';

    return isPositiveIgnition || isHybridGasoline ? 'category_1' : 'most_polluting';
}

function isEuro5OrAbove(standard: EuroStandard): boolean {
    return ['euro_1', 'euro_2', 'euro_3', 'euro_4'].indexOf(standard) === -1;
}

function expectedCategoryFromCritAir(vignette: CritAirVignette | null): PollutantCategory | null {
    if (vignette === null) {
        return null;
    }

    return {
        E: 'e' as const,
        '1': 'category_1' as const,
        '2': 'most_polluting' as const,
        '3': 'most_polluting' as const,
        '4': 'most_polluting' as const,
        '5': 'most_polluting' as const,
        unclassified: 'most_polluting' as const,
    }[vignette];
}

function labelOf(category: PollutantCategory): string {
    return {
        e: 'E · Électrique / hydrogène',
        category_1: '1 · Essence ou gaz Euro 5/6',
        most_polluting: 'Véhicules les plus polluants',
    }[category];
}

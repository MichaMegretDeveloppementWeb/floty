type EnergySource = App.Enums.Vehicle.EnergySource;
type EuroStandard = App.Enums.Vehicle.EuroStandard;
type UnderlyingCombustionEngineType = App.Enums.Vehicle.UnderlyingCombustionEngineType;
type PollutantCategory = App.Enums.Vehicle.PollutantCategory;

const STRICTLY_CLEAN: ReadonlyArray<EnergySource> = [
    'electric',
    'hydrogen',
    'electric_hydrogen',
];

const POSITIVE_IGNITION: ReadonlyArray<EnergySource> = [
    'gasoline',
    'lpg',
    'cng',
    'e85',
];

const HYBRID_SOURCES: ReadonlyArray<EnergySource> = [
    'plugin_hybrid',
    'non_plugin_hybrid',
];

const PRE_EURO_5: ReadonlyArray<EuroStandard> = [
    'euro_1',
    'euro_2',
    'euro_3',
    'euro_4',
];

/**
 * Mirroir TS de {@see App\Enums\Vehicle\PollutantCategory::derive()}.
 *
 * Sert exclusivement à l'affichage live de la catégorie résolue dans
 * le formulaire — la valeur réellement persistée est calculée côté
 * backend par le Repository, à partir des mêmes inputs. Les deux
 * implémentations doivent rester strictement équivalentes.
 *
 * Cas couverts :
 *  - source d'énergie strictement non-thermique → `e`
 *  - allumage commandé (essence/GPL/GNV/E85) Euro 5+ → `category_1`
 *  - hybride à sous-jacent essence Euro 5+ → `category_1`
 *  - tout le reste (Diesel, hybride à sous-jacent Diesel, sans norme,
 *    pré-Euro 5, hybride sans sous-jacent renseigné) → `most_polluting`
 */
export function derivePollutantCategory(
    energy: EnergySource,
    euro: EuroStandard | null | '',
    underlying: UnderlyingCombustionEngineType | null | '',
): PollutantCategory {
    if (STRICTLY_CLEAN.includes(energy)) {
        return 'e';
    }

    const euroNormalized = euro === '' ? null : euro;
    const underlyingNormalized = underlying === '' ? null : underlying;

    if (
        euroNormalized !== null
        && !PRE_EURO_5.includes(euroNormalized)
        && isPositiveIgnitionOrPositiveHybrid(energy, underlyingNormalized)
    ) {
        return 'category_1';
    }

    return 'most_polluting';
}

function isPositiveIgnitionOrPositiveHybrid(
    energy: EnergySource,
    underlying: UnderlyingCombustionEngineType | null,
): boolean {
    if (POSITIVE_IGNITION.includes(energy)) {
        return true;
    }

    if (HYBRID_SOURCES.includes(energy) && underlying === 'gasoline') {
        return true;
    }

    return false;
}

/**
 * Indique si l'énergie sélectionnée nécessite que l'utilisateur
 * renseigne le moteur thermique sous-jacent. Aligné sur
 * {@see EnergySource::requiresUnderlyingCombustionEngine()} côté PHP.
 */
export function requiresUnderlyingCombustionEngine(energy: EnergySource): boolean {
    return energy === 'plugin_hybrid'
        || energy === 'non_plugin_hybrid'
        || energy === 'electric_hydrogen';
}

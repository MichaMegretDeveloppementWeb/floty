import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    bodyTypeLabel,
    energySourceLabel,
    euroStandardLabel,
    homologationMethodLabel,
    pollutantCategoryLabel,
    receptionCategoryLabel,
    underlyingCombustionEngineTypeLabel,
    vehicleUserTypeLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Fiscal = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type StatItem = { value: string; label: string };

/**
 * Données dérivées de la card « Caractéristiques fiscales actives » :
 *   - `co2Display`     : choix d'affichage parmi WLTP / NEDC / PA
 *   - `stats`          : tableau dynamique des items grille (entrées
 *                        conditionnelles selon les champs renseignés)
 *   - `advancedFlags`  : badges des options actives (handicap, N1…)
 *   - `historyOpen`    : ouverture du modal d'historique
 *   - `historyCount`   : compteur affiché dans le label du bouton
 */
export function useCurrentFiscalCharacteristicsCard(props: {
    fiscal: Fiscal | null;
    history: Fiscal[];
}): {
    historyOpen: Ref<boolean>;
    historyCount: ComputedRef<number>;
    co2Display: ComputedRef<StatItem | null>;
    stats: ComputedRef<StatItem[]>;
    advancedFlags: ComputedRef<string[]>;
} {
    const historyOpen = ref<boolean>(false);
    const historyCount = computed<number>(() => props.history.length);

    const co2Display = computed<StatItem | null>(() => {
        const f = props.fiscal;

        if (!f) {
            return null;
        }

        if (f.co2Wltp !== null) {
            return { value: `${f.co2Wltp} g/km`, label: 'CO₂ WLTP' };
        }

        if (f.co2Nedc !== null) {
            return { value: `${f.co2Nedc} g/km`, label: 'CO₂ NEDC' };
        }

        if (f.taxableHorsepower !== null) {
            return {
                value: `${f.taxableHorsepower} CV`,
                label: 'Puissance admin.',
            };
        }

        return null;
    });

    const stats = computed<StatItem[]>(() => {
        const f = props.fiscal;

        if (!f) {
            return [];
        }

        const items: StatItem[] = [
            { value: receptionCategoryLabel[f.receptionCategory], label: 'Catégorie réception' },
            { value: vehicleUserTypeLabel[f.vehicleUserType], label: "Type d'usage" },
            { value: bodyTypeLabel[f.bodyType], label: 'Carrosserie' },
            { value: energySourceLabel[f.energySource], label: 'Énergie' },
            { value: homologationMethodLabel[f.homologationMethod], label: 'Méthode homologation' },
            { value: pollutantCategoryLabel[f.pollutantCategory], label: 'Catégorie polluants' },
            { value: `${f.seatsCount}`, label: 'Places assises' },
        ];

        if (f.euroStandard) {
            items.push({ value: euroStandardLabel[f.euroStandard], label: 'Norme Euro' });
        }

        if (co2Display.value) {
            items.push(co2Display.value);
        }

        if (f.kerbMass !== null) {
            items.push({ value: `${f.kerbMass} kg`, label: 'Masse à vide' });
        }

        if (f.underlyingCombustionEngineType) {
            items.push({
                value: underlyingCombustionEngineTypeLabel[f.underlyingCombustionEngineType],
                label: 'Moteur thermique sous-jacent',
            });
        }

        return items;
    });

    const advancedFlags = computed<string[]>(() => {
        const f = props.fiscal;

        if (!f) {
            return [];
        }

        const candidates: { active: boolean; label: string }[] = [
            { active: f.handicapAccess, label: 'Accès handicap' },
            { active: f.n1PassengerTransport, label: 'N1 transport voyageurs' },
            { active: f.n1RemovableSecondRowSeat, label: 'N1 banquette amovible' },
            { active: f.m1SpecialUse, label: 'M1 usage spécial' },
            { active: f.n1SkiLiftUse, label: 'N1 remontée mécanique' },
        ];

        return candidates.filter((c) => c.active).map((c) => c.label);
    });

    return { historyOpen, historyCount, co2Display, stats, advancedFlags };
}

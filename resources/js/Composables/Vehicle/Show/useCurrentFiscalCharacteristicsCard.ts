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
 * Item de la section « Exonérations, abattements et usages spéciaux »
 * sur la card · exhaustif (toutes les options **applicables** au
 * véhicule sont rendues, actives ou non) pour que l'utilisateur voie
 * la couverture complète sans avoir à ouvrir le formulaire.
 */
export type FiscalOptionItem = {
    label: string;
    hint: string;
    active: boolean;
};

/**
 * Données dérivées de la card « Caractéristiques fiscales actives ».
 *
 *   - `co2Display`         · choix d'affichage parmi WLTP / NEDC / PA
 *   - `stats`              · tableau dynamique des items grille
 *                            (entrées conditionnelles selon les champs
 *                            renseignés)
 *   - `applicableOptions`  · toutes les options « Exonérations,
 *                            abattements et usages spéciaux »
 *                            applicables au véhicule (filtrage M1/N1
 *                            + carrosserie · même logique que le
 *                            formulaire `FiscalCharacteristicsSection`),
 *                            avec leur état actif/inactif. P7 ·
 *                            permet de voir la couverture complète des
 *                            options, pas seulement les cochées.
 *   - `historyOpen`        · ouverture du modal d'historique
 *   - `historyCount`       · compteur affiché dans le label du bouton
 */
export function useCurrentFiscalCharacteristicsCard(props: {
    fiscal: Fiscal | null;
    history: Fiscal[];
}): {
    historyOpen: Ref<boolean>;
    historyCount: ComputedRef<number>;
    co2Display: ComputedRef<StatItem | null>;
    stats: ComputedRef<StatItem[]>;
    applicableOptions: ComputedRef<FiscalOptionItem[]>;
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

    const applicableOptions = computed<FiscalOptionItem[]>(() => {
        const f = props.fiscal;

        if (!f) {
            return [];
        }

        const isM1 = f.receptionCategory === 'M1';
        const isN1 = f.receptionCategory === 'N1';
        const isLightTruck = f.bodyType === 'CTTE';
        const isPickup = f.bodyType === 'BE';

        // Même filtrage que `FiscalCharacteristicsSection.vue` ·
        // garde-fou cohérence UI/Form (si on ajoute un flag au form,
        // le miroir s'aligne ici).
        const items: FiscalOptionItem[] = [
            {
                label: 'Aménagement handicap',
                hint: 'Fauteuil roulant ou conduite handicapée · exonération totale CO₂ + polluants (CIBS L. 421-123 / L. 421-136).',
                active: f.handicapAccess,
            },
            {
                label: 'Compatible E85',
                hint: 'Superéthanol flex-fuel (rubrique P.3 du certificat ∈ {FE, FG, FN, FL, FH, FR, FQ, FM, FP}) · dès 2025 abattement 40 % CO₂ ou 2 CV en PA (CIBS L. 421-125).',
                active: f.acceptsE85,
            },
        ];

        if (isM1) {
            items.push({
                label: 'M1 · usage spécial',
                hint: 'Corbillard, ambulance, véhicule blindé · hors champ fiscal des 2 taxes (CIBS L. 421-2).',
                active: f.m1SpecialUse,
            });
        }

        if (isN1 && isLightTruck) {
            items.push({
                label: 'N1 · 2ᵉ rangée amovible',
                hint: 'Banquette amovible installée sur camionnette N1.',
                active: f.n1RemovableSecondRowSeat,
            });
            items.push({
                label: 'N1 · transport de personnes',
                hint: 'Camionnette N1 affectée au transport de personnes · taxable seulement si combiné avec 2ᵉ rangée amovible (CIBS L. 421-2).',
                active: f.n1PassengerTransport,
            });
        }

        if (isN1 && isPickup) {
            items.push({
                label: 'N1 · remontées mécaniques',
                hint: 'Pick-up N1 affecté à l\'exploitation de remontées mécaniques · hors champ fiscal.',
                active: f.n1SkiLiftUse,
            });
        }

        return items;
    });

    return { historyOpen, historyCount, co2Display, stats, applicableOptions };
}

import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import {
    store as unavailabilitiesStoreRoute,
    update as unavailabilitiesUpdateRoute,
} from '@/routes/user/unavailabilities';
import {
    isUnavailabilityFiscallyReductive,
    unavailabilityTypeLabel,
} from '@/Utils/labels/unavailabilityEnumLabels';

type UnavailabilityType = App.Enums.Unavailability.UnavailabilityType;
type Unavailability = App.Data.User.Unavailability.UnavailabilityData;

type FormShape = {
    type: UnavailabilityType;
    start_date: string;
    end_date: string;
    description: string;
};

type DateRange = { startDate: string | null; endDate: string | null };

type SelectOption = { value: UnavailabilityType; label: string };

type SelectOptionGroup = {
    label: string;
    isReductive: boolean;
    options: SelectOption[];
};

/**
 * Compte le nombre de dates ISO de `busyDates` (jours déjà attribués
 * à un contrat actif) qui tombent dans la plage saisie par l'utilisateur.
 *
 * Cohabitation indispo↔contrat (ADR-0019) : la plage **peut** chevaucher
 * un contrat - cette fonction sert à alimenter l'encart info pédagogique
 * du modal, pas à bloquer la saisie.
 *
 * Sémantique :
 *   - `startDate === null` → 0 (plage incomplète, on n'a rien à compter)
 *   - `ongoing === false` et `endDate === null` → 0 (idem)
 *   - `ongoing === true` → on compte tous les `busyDates >= startDate`
 *     (la plage est considérée ouverte sur le futur, comme côté backend
 *     où `end_date IS NULL` désigne une indispo encore en cours)
 *   - sinon → on compte les `busyDates ∈ [startDate, endDate]` inclusif
 *
 * Pure pour faciliter le test unitaire - pas d'accès au composable.
 */
export function countConflictDaysInRange(
    busyDates: ReadonlyArray<string>,
    startDate: string | null,
    endDate: string | null,
    ongoing: boolean,
): number {
    if (startDate === null) {
        return 0;
    }

    if (!ongoing && endDate === null) {
        return 0;
    }

    return busyDates.filter((d) => {
        if (d < startDate) {
            return false;
        }

        if (!ongoing && endDate !== null && d > endDate) {
            return false;
        }

        return true;
    }).length;
}

/**
 * Form Inertia + UI state du modal de création/édition d'une
 * indisponibilité (ADR-0016 rev. 1.1, refonte chantier F).
 *
 *   - construit la grille `optionGroups` à 2 groupes (Réducteurs /
 *     Non réducteurs) consommée par le `<select>` de la modale ;
 *   - synchronise `range` et `ongoing` quand `props.editing` change
 *     (mode création vs édition) ;
 *   - calcule `canSubmit` (bouton désactivé tant que les bornes
 *     attendues ne sont pas posées) ;
 *   - calcule `selectedIsReductive` pour piloter le bandeau d'effet
 *     fiscal annoncé avant validation ;
 *   - applique `payloadTransform` (range+ongoing → snake_case backend) ;
 *   - dispatche le submit (POST store ou PATCH update selon le mode)
 *     puis ferme le modal et reset le state au success.
 */
export function useUnavailabilityForm(
    props: {
        vehicleId: number;
        editing: Unavailability | null;
        busyDates: string[];
    },
    open: Ref<boolean>,
): {
    optionGroups: SelectOptionGroup[];
    /**
     * Année à afficher dans le calendrier à l'ouverture. En création ·
     * année calendaire courante. En édition · l'année du `start_date`
     * de l'indispo éditée (P3 · sinon le calendrier reste sur 2026
     * alors qu'on édite une indispo de 2024).
     */
    viewYear: Ref<number>;
    form: InertiaForm<FormShape>;
    range: Ref<DateRange>;
    ongoing: Ref<boolean>;
    initialMonth: Ref<number>;
    isEditing: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    selectedIsReductive: ComputedRef<boolean>;
    conflictDaysCount: ComputedRef<number>;
    submit: () => void;
} {

    const buildOption = (value: UnavailabilityType): SelectOption => ({
        value,
        label: unavailabilityTypeLabel[value],
    });

    const optionGroups: SelectOptionGroup[] = [
        {
            label: 'Réduit la taxe',
            isReductive: true,
            options: [
                buildOption('accident_no_circulation'),
                buildOption('pound_public'),
                buildOption('ci_suspension'),
            ],
        },
        {
            label: 'Sans effet fiscal',
            isReductive: false,
            options: [
                buildOption('maintenance'),
                buildOption('technical_inspection'),
                buildOption('accident_repair'),
                buildOption('pound_private'),
                buildOption('theft'),
                buildOption('other'),
            ],
        },
    ];

    const form = useForm<FormShape>({
        type: 'maintenance',
        start_date: '',
        end_date: '',
        description: '',
    });

    const range = ref<DateRange>({ startDate: null, endDate: null });
    const ongoing = ref<boolean>(false);

    // P3 · vue initiale du DateRangePicker (mois + année) · dérivée du
    // startDate de l'indispo en cours d'édition pour que le calendrier
    // s'ouvre exactement sur la période sélectionnée. En création ·
    // mois et année calendaires courants (chantier η Phase 3 doctrine ·
    // l'utilisateur saisit dans son présent).
    const now = new Date();
    const initialMonth = ref<number>(now.getMonth() + 1);
    const viewYear = ref<number>(now.getFullYear());

    watch(
        () => props.editing,
        (value) => {
            if (value) {
                form.type = value.type;
                form.description = value.description ?? '';
                range.value = {
                    startDate: value.startDate,
                    endDate: value.endDate,
                };
                ongoing.value = value.endDate === null;
                // P3 · ouvrir le calendrier sur l'année ET le mois du
                // startDate de l'indispo éditée. Sans ça, une indispo
                // 2024 s'affichait avec un calendrier 2026 vide ·
                // l'utilisateur ne voyait pas la période sélectionnée.
                viewYear.value = Number(value.startDate.slice(0, 4));
                initialMonth.value = Number(value.startDate.slice(5, 7));
            } else {
                form.reset();
                form.type = 'maintenance';
                range.value = { startDate: null, endDate: null };
                ongoing.value = false;
                // Reset sur le présent calendaire (création).
                const today = new Date();
                viewYear.value = today.getFullYear();
                initialMonth.value = today.getMonth() + 1;
            }

            form.clearErrors();
        },
    );

    const isEditing = computed<boolean>(() => props.editing !== null);

    const canSubmit = computed<boolean>(() => {
        if (range.value.startDate === null) {
            return false;
        }

        if (!ongoing.value && range.value.endDate === null) {
            return false;
        }

        return true;
    });

    const selectedIsReductive = computed<boolean>(() =>
        isUnavailabilityFiscallyReductive(form.type),
    );

    const conflictDaysCount = computed<number>(() =>
        countConflictDaysInRange(
            props.busyDates,
            range.value.startDate,
            range.value.endDate,
            ongoing.value,
        ),
    );

    const payloadTransform = (data: {
        type: UnavailabilityType;
        description: string;
    }): Record<string, unknown> => ({
        type: data.type,
        start_date: range.value.startDate,
        end_date: ongoing.value ? null : range.value.endDate,
        description: data.description === '' ? null : data.description,
    });

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        if (isEditing.value && props.editing) {
            form.transform(payloadTransform).patch(
                unavailabilitiesUpdateRoute.url({
                    unavailability: props.editing.id,
                }),
                closeOnSuccess(open),
            );

            return;
        }

        form.transform((data) => ({
            ...payloadTransform(data),
            vehicle_id: props.vehicleId,
        })).post(unavailabilitiesStoreRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
                form.type = 'maintenance';
                range.value = { startDate: null, endDate: null };
                ongoing.value = false;
            },
        });
    };

    return {
        optionGroups,
        viewYear,
        form,
        range,
        ongoing,
        initialMonth,
        isEditing,
        canSubmit,
        selectedIsReductive,
        conflictDaysCount,
        submit,
    };
}

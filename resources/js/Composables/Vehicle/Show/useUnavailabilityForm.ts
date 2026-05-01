import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
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
    currentYear: ComputedRef<number>;
    form: InertiaForm<FormShape>;
    range: Ref<DateRange>;
    ongoing: Ref<boolean>;
    isEditing: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    selectedIsReductive: ComputedRef<boolean>;
    submit: () => void;
} {
    const { currentYear } = useFiscalYear();

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
            } else {
                form.reset();
                form.type = 'maintenance';
                range.value = { startDate: null, endDate: null };
                ongoing.value = false;
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
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        open.value = false;
                    },
                },
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
        currentYear,
        form,
        range,
        ongoing,
        isEditing,
        canSubmit,
        selectedIsReductive,
        submit,
    };
}

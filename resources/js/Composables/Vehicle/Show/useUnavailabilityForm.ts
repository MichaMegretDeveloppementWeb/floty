import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import {
    store as unavailabilitiesStoreRoute,
    update as unavailabilitiesUpdateRoute,
} from '@/routes/user/unavailabilities';
import { unavailabilityTypeLabel } from '@/Utils/labels/unavailabilityEnumLabels';

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

/**
 * Form Inertia + UI state du modal de création/édition d'une
 * indisponibilité. Le composable :
 *
 *   - synchronise `range` et `ongoing` quand `props.editing` change
 *     (mode création vs édition)
 *   - calcule `canSubmit` (bouton désactivé tant que les bornes
 *     attendues ne sont pas posées)
 *   - applique `payloadTransform` qui mappe `range`+`ongoing` vers
 *     les champs snake_case attendus côté backend
 *   - dispatche le submit (POST store ou PATCH update selon le mode)
 *     puis ferme le modal et reset le state au success
 */
export function useUnavailabilityForm(
    props: {
        vehicleId: number;
        editing: Unavailability | null;
        busyDates: string[];
    },
    open: Ref<boolean>,
): {
    typeOptions: SelectOption[];
    currentYear: ComputedRef<number>;
    form: InertiaForm<FormShape>;
    range: Ref<DateRange>;
    ongoing: Ref<boolean>;
    isEditing: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const { currentYear } = useFiscalYear();

    const typeOptions: SelectOption[] = (
        Object.keys(unavailabilityTypeLabel) as UnavailabilityType[]
    ).map((value) => ({
        value,
        label: unavailabilityTypeLabel[value],
    }));

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
        typeOptions,
        currentYear,
        form,
        range,
        ongoing,
        isEditing,
        canSubmit,
        submit,
    };
}

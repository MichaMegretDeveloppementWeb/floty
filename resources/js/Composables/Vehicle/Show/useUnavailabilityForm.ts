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
 * Counts ISO dates in `busyDates` (days already assigned to an active contract) that fall
 * within the user's range.
 *
 * Unavailability ↔ contract cohabitation (ADR-0019): the range MAY overlap a contract;
 * this function feeds the modal's pedagogical info banner, it does not block input.
 *
 * Semantics:
 *   - `startDate === null` → 0 (incomplete range)
 *   - `!ongoing && endDate === null` → 0 (idem)
 *   - `ongoing === true` → count all `busyDates >= startDate` (open-ended range, matching
 *     backend `end_date IS NULL` for an ongoing unavailability)
 *   - otherwise → count `busyDates ∈ [startDate, endDate]` inclusive
 *
 * Pure to ease unit testing (no composable access).
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
 * Inertia form + UI state for the unavailability create/edit modal (ADR-0016 rev. 1.1).
 *
 *   - builds the 2-group `optionGroups` grid (Reducing / Non-reducing) consumed by the modal `<select>`,
 *   - synchronises `range` and `ongoing` when `props.editing` changes (create vs edit mode),
 *   - computes `canSubmit` (button disabled until the expected bounds are set),
 *   - computes `selectedIsReductive` to drive the fiscal-effect banner before submit,
 *   - applies `payloadTransform` (range+ongoing → snake_case backend),
 *   - dispatches submit (POST store or PATCH update depending on mode) and closes/resets on success.
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
     * Year to display in the calendar on opening. Create mode: current calendar year.
     * Edit mode: the year of the edited unavailability's `start_date`
     * (otherwise the calendar stays on the current year while editing an older unavailability).
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

    // Initial DateRangePicker view (month + year): derived from the edited unavailability's startDate
    // so the calendar opens on the selected period. Create mode: current calendar month + year.
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
                // Open the calendar on the year AND month of the edited unavailability's startDate.
                // Without this, editing a 2024 unavailability showed an empty 2026 calendar and the
                // user could not see the selected period.
                viewYear.value = Number(value.startDate.slice(0, 4));
                initialMonth.value = Number(value.startDate.slice(5, 7));
            } else {
                form.reset();
                form.type = 'maintenance';
                range.value = { startDate: null, endDate: null };
                ongoing.value = false;
                // Reset to the calendar present (create mode).
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

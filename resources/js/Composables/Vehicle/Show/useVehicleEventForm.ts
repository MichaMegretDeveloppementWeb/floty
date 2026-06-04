import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    store as vehicleEventsStoreRoute,
    update as vehicleEventsUpdateRoute,
} from '@/routes/user/vehicle-events';
import {
    isVehicleEventFiscallyReductive,
    vehicleEventTypeLabel,
} from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEventType = App.Enums.VehicleEvent.VehicleEventType;
type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

type FormShape = {
    type: VehicleEventType;
    // Custom identity, used only when type === 'other' (sent null otherwise).
    title: string;
    category: string;
    // Informative unavailability flag (user-controlled only for 'other').
    implies_unavailability: boolean;
    start_date: string;
    end_date: string;
    description: string;
};

type DateRange = { startDate: string | null; endDate: string | null };

type SelectOption = { value: VehicleEventType; label: string };

type SelectOptionGroup = {
    label: string;
    isReductive: boolean;
    options: SelectOption[];
};

/**
 * Counts ISO dates in `busyDates` (days already assigned to an active contract) that fall
 * within the user's range.
 *
 * VehicleEvent ↔ contract cohabitation (ADR-0019): the range MAY overlap a contract;
 * this function feeds the modal's pedagogical info banner, it does not block input.
 *
 * Semantics:
 *   - `startDate === null` → 0 (incomplete range)
 *   - `!ongoing && endDate === null` → 0 (idem)
 *   - `ongoing === true` → count all `busyDates >= startDate` (open-ended range, matching
 *     backend `end_date IS NULL` for an ongoing vehicle event)
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
 * Inertia form + UI state for the vehicle event create/edit page (ADR-0016 rev. 1.1).
 *
 *   - builds the 3-group `optionGroups` list (Custom / Reducing / Non-reducing) consumed by the form `<select>`,
 *   - synchronises `range` and `ongoing` when `props.editing` changes (create vs edit mode),
 *   - computes `canSubmit` (button disabled until the expected bounds are set),
 *   - computes `selectedIsReductive` to drive the fiscal-effect banner before submit,
 *   - applies `payloadTransform` (range+ongoing → snake_case backend),
 *   - dispatches submit (POST store or PATCH update); the controller redirects
 *     to the vehicle "Événements" tab on success, Inertia keeps the page and
 *     `form.errors` on validation failure.
 */
export function useVehicleEventForm(
    props: {
        vehicleId: number;
        editing: VehicleEvent | null;
        busyDates: string[];
    },
    options: {
        /** Files queued in create mode, sent with the multipart create request. */
        pendingDocuments?: Ref<File[]>;
        /**
         * ISO `YYYY-MM-DD` pre-selected in create mode (start = end), set when
         * the user opened the form from a specific timeline day. Ignored in edit.
         */
        initialDate?: string;
    } = {},
): {
    optionGroups: SelectOptionGroup[];
    /**
     * Year to display in the calendar on opening. Create mode: current calendar year.
     * Edit mode: the year of the edited vehicle event's `start_date`
     * (otherwise the calendar stays on the current year while editing an older vehicle event).
     */
    viewYear: Ref<number>;
    form: InertiaForm<FormShape>;
    range: Ref<DateRange>;
    ongoing: Ref<boolean>;
    initialMonth: Ref<number>;
    isEditing: ComputedRef<boolean>;
    /** True when the custom "other" type is selected (reveals title/category/flag fields). */
    isCustom: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    selectedIsReductive: ComputedRef<boolean>;
    conflictDaysCount: ComputedRef<number>;
    submit: () => void;
} {

    const buildOption = (value: VehicleEventType): SelectOption => ({
        value,
        label: vehicleEventTypeLabel[value],
    });

    const optionGroups: SelectOptionGroup[] = [
        {
            label: 'Personnalisé',
            isReductive: false,
            options: [
                buildOption('other'),
            ],
        },
        {
            label: 'Indisponibilité fiscalement réductrice',
            isReductive: true,
            options: [
                buildOption('accident_no_circulation'),
                buildOption('pound_public'),
                buildOption('ci_suspension'),
            ],
        },
        {
            label: 'Indisponibilité non réductrice',
            isReductive: false,
            options: [
                buildOption('maintenance'),
                buildOption('technical_inspection'),
                buildOption('accident_repair'),
                buildOption('pound_private'),
                buildOption('theft'),
            ],
        },
    ];

    const form = useForm<FormShape>({
        type: 'other',
        title: '',
        category: '',
        implies_unavailability: true,
        start_date: '',
        end_date: '',
        description: '',
    });

    const range = ref<DateRange>({ startDate: null, endDate: null });
    const ongoing = ref<boolean>(false);

    // Initial DateRangePicker view (month + year): derived from the edited vehicle event's startDate
    // so the calendar opens on the selected period. Create mode: current calendar month + year.
    const now = new Date();
    const initialMonth = ref<number>(now.getMonth() + 1);
    const viewYear = ref<number>(now.getFullYear());

    watch(
        () => props.editing,
        (value) => {
            if (value) {
                form.type = value.type;
                form.title = value.title ?? '';
                form.category = value.category ?? '';
                form.implies_unavailability = value.impliesUnavailability;
                form.description = value.description ?? '';
                range.value = {
                    startDate: value.startDate,
                    endDate: value.endDate,
                };
                ongoing.value = value.endDate === null;
                // Open the calendar on the year AND month of the edited vehicle event's startDate.
                // Without this, editing a 2024 vehicle event showed an empty 2026 calendar and the
                // user could not see the selected period.
                viewYear.value = Number(value.startDate.slice(0, 4));
                initialMonth.value = Number(value.startDate.slice(5, 7));
            } else {
                form.reset();
                form.type = 'other';

                if (options.initialDate) {
                    // Create from a specific timeline day: pre-select start = end
                    // and open the calendar on that day.
                    range.value = { startDate: options.initialDate, endDate: options.initialDate };
                    ongoing.value = false;
                    viewYear.value = Number(options.initialDate.slice(0, 4));
                    initialMonth.value = Number(options.initialDate.slice(5, 7));
                } else {
                    range.value = { startDate: null, endDate: null };
                    ongoing.value = false;
                    // Reset to the calendar present (create mode).
                    const today = new Date();
                    viewYear.value = today.getFullYear();
                    initialMonth.value = today.getMonth() + 1;
                }
            }

            form.clearErrors();
        },
        // Immediate: on the dedicated form pages `props.editing` is set once at
        // mount (it never changes), so the watcher must run on mount to
        // pre-fill (edit) or reset (create) the form.
        { immediate: true },
    );

    const isEditing = computed<boolean>(() => props.editing !== null);

    const isCustom = computed<boolean>(() => form.type === 'other');

    const canSubmit = computed<boolean>(() => {
        if (range.value.startDate === null) {
            return false;
        }

        if (!ongoing.value && range.value.endDate === null) {
            return false;
        }

        // Custom "other" events require a free name and category.
        if (
            isCustom.value
            && (form.title.trim() === '' || form.category.trim() === '')
        ) {
            return false;
        }

        return true;
    });

    const selectedIsReductive = computed<boolean>(() =>
        isVehicleEventFiscallyReductive(form.type),
    );

    const conflictDaysCount = computed<number>(() =>
        countConflictDaysInRange(
            props.busyDates,
            range.value.startDate,
            range.value.endDate,
            ongoing.value,
        ),
    );

    const payloadTransform = (data: FormShape): Record<string, unknown> => {
        const isOther = data.type === 'other';

        return {
            type: data.type,
            // Custom identity only for 'other'; known types derive it on display.
            title: isOther && data.title.trim() !== '' ? data.title.trim() : null,
            category: isOther && data.category.trim() !== '' ? data.category.trim() : null,
            // Known types always imply unavailability; only 'other' may opt out.
            implies_unavailability: isOther ? data.implies_unavailability : true,
            start_date: range.value.startDate,
            end_date: ongoing.value ? null : range.value.endDate,
            description: data.description === '' ? null : data.description,
        };
    };

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        if (isEditing.value && props.editing) {
            form.transform(payloadTransform).patch(
                vehicleEventsUpdateRoute.url({
                    vehicleEvent: props.editing.id,
                }),
            );

            return;
        }

        form.transform((data) => ({
            ...payloadTransform(data),
            vehicle_id: props.vehicleId,
            // Files queued during creation travel with the multipart request;
            // Inertia switches to FormData automatically when files are present.
            documents: options.pendingDocuments?.value ?? [],
        })).post(vehicleEventsStoreRoute.url());
    };

    return {
        optionGroups,
        viewYear,
        form,
        range,
        ongoing,
        initialMonth,
        isEditing,
        isCustom,
        canSubmit,
        selectedIsReductive,
        conflictDaysCount,
        submit,
    };
}

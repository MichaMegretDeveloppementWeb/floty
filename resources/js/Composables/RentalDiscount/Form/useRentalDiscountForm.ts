/**
 * Form composable for Create/Edit RentalDiscount.
 *
 * Wraps:
 *  - Inertia `useForm` (state + submit + errors)
 *  - UI percent ↔ DB basis points conversion (1 050 bp = 10.50 %)
 *  - Live debounced overlap check via POST /check-conflicts
 *
 * `useForm` keys are snake_case to match backend (Spatie Data + `SnakeCaseMapper`).
 * Without this, the server can't find input fields (required validation fails) AND
 * returned `form.errors` use snake_case, so `<InputError :message="form.errors.start_date" />`
 * would not resolve if bound in camelCase.
 */

import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { DateRange } from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
import { checkConflicts as checkConflictsRoute } from '@/routes/user/rental-discounts';

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]!) : '';
}

export type ConflictItem = {
    id: number;
    label: string | null;
    startDate: string;
    endDate: string;
    discountBasisPoints: number;
    vehiclesCount: number;
    isAllVehicles: boolean;
};

export type RentalDiscountFormPayload = {
    company_id: number | null;
    start_date: string;
    end_date: string;
    discount_basis_points: number;
    label: string | null;
    notes: string | null;
    vehicle_ids: number[];
};

export type RentalDiscountFormInitial = {
    /** Pre-filled in Edit; null in Create. */
    id: number | null;
    /** Pre-filled in Edit (parent companyId); null in Create. */
    companyId: number | null;
    startDate: string;
    endDate: string;
    /** UI percent (0..100). Converted to bp on submit. */
    discountPercent: number;
    label: string | null;
    notes: string | null;
    vehicleIds: number[];
};

export function useRentalDiscountForm(
    initial: RentalDiscountFormInitial,
    onSubmit: (form: ReturnType<typeof useForm>) => void,
): {
    form: ReturnType<typeof useForm>;
    discountPercent: Ref<number>;
    appliesToAllVehicles: Ref<boolean>;
    range: Ref<DateRange>;
    /** "Open-ended" toggle required by the DateRangePicker; always `false` here (a discount always has an end date). */
    ongoing: Ref<boolean>;
    /** Initial DateRangePicker year (centred on existing start_date or current year). */
    pickerInitialYear: number;
    /** Initial DateRangePicker month (1..12, centred on existing start_date or current month). */
    pickerInitialMonth: number;
    conflicts: Ref<ConflictItem[]>;
    isCheckingConflicts: Ref<boolean>;
    hasConflicts: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    // User enters %, we convert to bp on submit.
    const discountPercent = ref<number>(initial.discountPercent);

    // "Applies to all company vehicles" toggle: `true` = empty list (means "all").
    // Initialised from the original state (empty vehicleIds).
    const appliesToAllVehicles = ref<boolean>(initial.vehicleIds.length === 0);

    // DateRangePicker consumes `range` (v-model) and `ongoing` (v-model).
    // `ongoing` stays constant `false`: a discount always has an end date (unlike unavailabilities).
    const range = ref<DateRange>({
        startDate: initial.startDate !== '' ? initial.startDate : null,
        endDate: initial.endDate !== '' ? initial.endDate : null,
    });
    const ongoing = ref<boolean>(false);

    // Initial calendar year/month: centred on existing date (Edit) or current date (Create).
    // Stable after init: the DateRangePicker keeps its own internal state from there.
    const today = new Date();
    const startSource = initial.startDate !== '' ? new Date(initial.startDate) : today;
    const pickerInitialYear = startSource.getFullYear();
    const pickerInitialMonth = startSource.getMonth() + 1;

    // Inertia form with snake_case keys (see top doc).
    const form = useForm<RentalDiscountFormPayload>({
        company_id: initial.companyId,
        start_date: initial.startDate,
        end_date: initial.endDate,
        discount_basis_points: Math.round(initial.discountPercent * 100),
        label: initial.label,
        notes: initial.notes,
        vehicle_ids: initial.vehicleIds,
    });

    // Sync UI percent → bp form payload.
    watch(discountPercent, (next) => {
        form.discount_basis_points = Math.round(next * 100);
    });

    // Sync `range` (calendar selection) → form payload (snake_case).
    watch(range, (next) => {
        form.start_date = next.startDate ?? '';
        form.end_date = next.endDate ?? '';
    }, { deep: true });

    // "All vehicles" toggle: checking clears the list; unchecking leaves it for the user to fill.
    watch(appliesToAllVehicles, (next) => {
        if (next) {
            form.vehicle_ids = [];
        }
    });

    // Debounced overlap check (400 ms).
    const conflicts = ref<ConflictItem[]>([]);
    const isCheckingConflicts = ref<boolean>(false);
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function debouncedCheckConflicts(): void {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            void runCheckConflicts();
        }, 400);
    }

    async function runCheckConflicts(): Promise<void> {
        if (
            form.company_id === null
            || form.start_date === ''
            || form.end_date === ''
            || form.start_date > form.end_date
        ) {
            conflicts.value = [];
            return;
        }

        isCheckingConflicts.value = true;
        try {
            // Direct fetch to bypass useApi's automatic error toast: silent failure is acceptable here,
            // the backend submit re-checks anyway (defense in depth).
            const response = await fetch(checkConflictsRoute.url(), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    company_id: form.company_id,
                    start_date: form.start_date,
                    end_date: form.end_date,
                    vehicle_ids: appliesToAllVehicles.value ? [] : form.vehicle_ids,
                    exclude_id: initial.id,
                }),
            });
            if (!response.ok) {
                return;
            }
            const body = (await response.json()) as {
                hasConflicts: boolean;
                conflicts: ConflictItem[];
            };
            conflicts.value = body.conflicts;
        } catch {
            // Network error: keep the previous list.
        } finally {
            isCheckingConflicts.value = false;
        }
    }

    // Trigger the check on each mutation of relevant fields.
    watch(
        [
            () => form.company_id,
            () => form.start_date,
            () => form.end_date,
            () => form.vehicle_ids,
            appliesToAllVehicles,
        ],
        () => debouncedCheckConflicts(),
        { deep: true },
    );

    const hasConflicts = computed<boolean>(() => conflicts.value.length > 0);

    const canSubmit = computed<boolean>(() => {
        if (form.processing) {
            return false;
        }
        if (hasConflicts.value) {
            return false;
        }
        if (form.company_id === null) {
            return false;
        }
        if (form.start_date === '' || form.end_date === '') {
            return false;
        }
        if (form.start_date > form.end_date) {
            return false;
        }
        if (form.discount_basis_points < 1 || form.discount_basis_points > 10_000) {
            return false;
        }
        return true;
    });

    function submit(): void {
        onSubmit(form);
    }

    return {
        form,
        discountPercent,
        appliesToAllVehicles,
        range,
        ongoing,
        pickerInitialYear,
        pickerInitialMonth,
        conflicts,
        isCheckingConflicts,
        hasConflicts,
        canSubmit,
        submit,
    };
}

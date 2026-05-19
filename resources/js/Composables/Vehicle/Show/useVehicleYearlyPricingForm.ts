import type { InertiaForm } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import { destroy as pricingDestroyRoute, store as pricingStoreRoute } from '@/routes/user/vehicle-yearly-pricings';

type Pricing = App.Data.User.Vehicle.VehicleYearlyPricingData;

export type PricingFormShape = {
    year: number;
    dailyRateEuros: number | null;
    weeklyRateEuros: number | null;
    monthlyRateEuros: number | null;
};

/**
 * Builds the form's initial state depending on mode:
 *   - edit (`editing` non-null): cents → € conversion for the 3 rates; year comes from the existing DTO (UI-locked).
 *   - create: preload `currentYear` if present in available years, otherwise the first available,
 *     finally falling back to `currentYear` (degenerate empty list, button disabled upstream).
 *
 * Pure (testable without mocking Inertia).
 */
export function buildPricingFormInitialState(
    editing: Pricing | null,
    availableYears: ReadonlyArray<number>,
    currentYear: number,
): PricingFormShape {
    if (editing) {
        return {
            year: editing.year,
            dailyRateEuros: editing.dailyRateCents / 100,
            weeklyRateEuros: editing.weeklyRateCents / 100,
            monthlyRateEuros: editing.monthlyRateCents / 100,
        };
    }

    const fallback = availableYears.includes(currentYear)
        ? currentYear
        : (availableYears[0] ?? currentYear);

    return {
        year: fallback,
        dailyRateEuros: null,
        weeklyRateEuros: null,
        monthlyRateEuros: null,
    };
}

/**
 * UI-side validation rule, executed on each keystroke to enable/disable the Save button.
 * Backend re-catches these via Spatie Data; blocking upstream avoids a server round-trip on
 * obvious invalid inputs (empty, negative).
 *
 * Note: a rate of 0 is valid (courtesy car / internal free use).
 */
export function isPricingFormValid(form: PricingFormShape, processing: boolean): boolean {
    if (processing) {
        return false;
    }

    if (form.year <= 0) {
        return false;
    }

    if (
        form.dailyRateEuros === null
        || form.weeklyRateEuros === null
        || form.monthlyRateEuros === null
    ) {
        return false;
    }

    if (
        form.dailyRateEuros < 0
        || form.weeklyRateEuros < 0
        || form.monthlyRateEuros < 0
    ) {
        return false;
    }

    return true;
}

/**
 * Converts the €-displayed form to the cents backend payload
 * (snake_case, expected by Spatie Data via `MapInputName(SnakeCaseMapper)`).
 *
 * `Math.round()` is critical: `1.10 * 100 === 110.00000000000001` in IEEE 754 and
 * `Math.trunc()` would return 109, losing one cent on every "round" rate entered with decimals.
 */
export function transformPricingFormToPayload(form: PricingFormShape): {
    year: number;
    daily_rate_cents: number;
    weekly_rate_cents: number;
    monthly_rate_cents: number;
} {
    return {
        year: form.year,
        daily_rate_cents: Math.round((form.dailyRateEuros ?? 0) * 100),
        weekly_rate_cents: Math.round((form.weeklyRateEuros ?? 0) * 100),
        monthly_rate_cents: Math.round((form.monthlyRateEuros ?? 0) * 100),
    };
}

/**
 * Inertia form + UI state for the create/edit modal of a daily/weekly/monthly rate for a given year.
 *
 * Values are displayed in € on the UI but transmitted in cents to the backend
 * (`unsignedInteger` in DB). The bidirectional conversion is centralised here:
 * cents → € at init when editing an existing pricing, and € → cents at submit (`Math.round(€ * 100)`).
 *
 * The store route on `(vehicle_id, year)` is idempotent (`updateOrCreate`), so the same endpoint serves both modes.
 */
export function useVehicleYearlyPricingForm(
    props: {
        vehicleId: number;
        availableYears: ReadonlyArray<number>;
        editing: Pricing | null;
    },
    open: Ref<boolean>,
): {
    form: InertiaForm<PricingFormShape>;
    isEditing: ComputedRef<boolean>;
    yearOptions: ComputedRef<ReadonlyArray<{ value: number; label: string }>>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const currentYear = new Date().getFullYear();

    const buildInitialState = (): PricingFormShape =>
        buildPricingFormInitialState(props.editing, props.availableYears, currentYear);

    const form = useForm<PricingFormShape>(buildInitialState());

    const isEditing = computed<boolean>(() => props.editing !== null);

    const yearOptions = computed<ReadonlyArray<{ value: number; label: string }>>(() => {
        // In edit mode the year is locked but still exposed as the sole option so the SelectInput
        // can render its label.
        if (isEditing.value && props.editing) {
            return [{ value: props.editing.year, label: String(props.editing.year) }];
        }

        return props.availableYears.map((year) => ({
            value: year,
            label: String(year),
        }));
    });

    // Reset the form to mode-appropriate values on each opening (create vs edit).
    watch(open, (isOpen) => {
        if (isOpen) {
            Object.assign(form, buildInitialState());
            form.clearErrors();
        }
    });

    const canSubmit = computed<boolean>(() =>
        isPricingFormValid(
            {
                year: form.year,
                dailyRateEuros: form.dailyRateEuros,
                weeklyRateEuros: form.weeklyRateEuros,
                monthlyRateEuros: form.monthlyRateEuros,
            },
            form.processing,
        ),
    );

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        form.transform(transformPricingFormToPayload).post(
            pricingStoreRoute.url({ vehicle: props.vehicleId }),
            closeOnSuccess(open),
        );
    };

    return {
        form,
        isEditing,
        yearOptions,
        canSubmit,
        submit,
    };
}

/**
 * Helper used by the parent partial to drive the create/edit modal.
 * `editing` is `null` in create mode and points to the existing DTO in edit mode.
 * The child modal calls `useVehicleYearlyPricingForm()` which adapts to the mode automatically.
 */
export function useVehiclePricingFormModalState(): {
    open: Ref<boolean>;
    editing: Ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>;
    requestCreate: () => void;
    requestEdit: (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData) => void;
} {
    const open = ref<boolean>(false);
    const editing = ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>(null);

    const requestCreate = (): void => {
        editing.value = null;
        open.value = true;
    };

    const requestEdit = (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData): void => {
        editing.value = pricing;
        open.value = true;
    };

    return { open, editing, requestCreate, requestEdit };
}

/**
 * Helper driving the delete confirmation modal. Stores the pricing to delete and exposes
 * `confirmDelete()` which dispatches the Wayfinder DELETE to the backend.
 */
export function useVehiclePricingDeleteState(vehicleId: number): {
    open: Ref<boolean>;
    deleting: Ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>;
    processing: Ref<boolean>;
    requestDelete: (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData) => void;
    confirmDelete: () => void;
} {
    const open = ref<boolean>(false);
    const deleting = ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>(null);
    const processing = ref<boolean>(false);

    const requestDelete = (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData): void => {
        deleting.value = pricing;
        open.value = true;
    };

    const confirmDelete = (): void => {
        if (deleting.value === null) {
            return;
        }

        processing.value = true;

        router.delete(
            pricingDestroyRoute.url({ vehicle: vehicleId, year: deleting.value.year }),
            {
                preserveScroll: true,
                onFinish: () => {
                    processing.value = false;
                    open.value = false;
                    deleting.value = null;
                },
            },
        );
    };

    return { open, deleting, processing, requestDelete, confirmDelete };
}

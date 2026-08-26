/**
 * Shortcut picker routing to a company tab for a given exercise, used by
 * the Invoices index, the Declarations index and the dashboard panels.
 *
 * The payload is served as `Inertia::optional()` so no screen pays for
 * it at mount: `ensureLoaded()` pulls it through a partial reload the
 * first time the modal opens, then it stays in the page props (partial
 * reloads merge, they do not replace).
 */

import type { PageProps } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { SelectOption } from '@/Composables/Ui/SearchableSelect/useSearchableSelect';
import { show as companyShow } from '@/routes/user/companies';

type PickerData = App.Data.User.Company.CompanyYearPickerData;

type PickerPageProps = PageProps & {
    companyYearPicker?: PickerData;
};

/** Company tab the picker routes to. */
export type CompanyYearPickerTarget = 'billing' | 'fiscal';

/**
 * Preselected exercise once the payload lands. Billing runs during the
 * exercise, a declaration only covers a closed one.
 */
export type CompanyYearPickerDefault = 'current' | 'previous';

export function useCompanyYearPicker(options: {
    target: CompanyYearPickerTarget;
    defaultYear: CompanyYearPickerDefault;
}): {
    isLoading: Ref<boolean>;
    isReady: ComputedRef<boolean>;
    hasCompanies: ComputedRef<boolean>;
    companyOptions: ComputedRef<SelectOption[]>;
    yearOptions: ComputedRef<SelectOption[]>;
    companyId: Ref<number | null>;
    year: Ref<number | null>;
    canSubmit: ComputedRef<boolean>;
    ensureLoaded: () => void;
    submit: () => void;
} {
    const page = usePage<PickerPageProps>();

    const isLoading = ref<boolean>(false);
    const companyId = ref<number | null>(null);
    const year = ref<number | null>(null);

    const payload = computed<PickerData | undefined>(
        () => page.props.companyYearPicker,
    );

    const isReady = computed<boolean>(() => payload.value !== undefined);

    const hasCompanies = computed<boolean>(
        () => (payload.value?.companies.length ?? 0) > 0,
    );

    const companyOptions = computed<SelectOption[]>(
        () =>
            payload.value?.companies.map((company) => ({
                value: company.id,
                label: `${company.shortCode} · ${company.legalName}`,
            })) ?? [],
    );

    // Most recent exercise first, like every other year selector.
    const yearOptions = computed<SelectOption[]>(
        () =>
            [...(payload.value?.years ?? [])]
                .sort((a, b) => b - a)
                .map((value) => ({ value, label: String(value) })),
    );

    const canSubmit = computed<boolean>(
        () => companyId.value !== null && year.value !== null,
    );

    function resolveDefaultYear(data: PickerData): number | null {
        if (data.years.length === 0) {
            return null;
        }

        const latest = Math.max(...data.years);

        if (options.defaultYear === 'current') {
            return latest;
        }

        const previous = data.currentYear - 1;

        return data.years.includes(previous) ? previous : latest;
    }

    watch(
        payload,
        (data) => {
            if (data === undefined || year.value !== null) {
                return;
            }

            year.value = resolveDefaultYear(data);
        },
        { immediate: true },
    );

    function ensureLoaded(): void {
        if (isReady.value || isLoading.value) {
            return;
        }

        isLoading.value = true;

        router.reload({
            only: ['companyYearPicker'],
            onFinish: () => {
                isLoading.value = false;
            },
        });
    }

    function submit(): void {
        if (companyId.value === null || year.value === null) {
            return;
        }

        router.visit(
            companyShow(companyId.value, {
                query: { tab: options.target, year: year.value },
            }).url,
        );
    }

    return {
        isLoading,
        isReady,
        hasCompanies,
        companyOptions,
        yearOptions,
        companyId,
        year,
        canSubmit,
        ensureLoaded,
        submit,
    };
}

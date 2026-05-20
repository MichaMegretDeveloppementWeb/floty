<script setup lang="ts">
/**
 * Billing tab on the Company detail page: editorial header, year tabs,
 * revenue hero, KPI stats row and a flush monthly breakdown table.
 */
import { router } from '@inertiajs/vue3';
import { BadgePercent } from 'lucide-vue-next';
import { computed } from 'vue';
import BulkGenerateInvoicesButton from '@/Components/Domain/Billing/BulkGenerateInvoicesButton.vue';
import BulkInvoiceGenerationReportAlert from '@/Components/Domain/Billing/BulkInvoiceGenerationReportAlert.vue';
import GenerateInvoiceButton from '@/Components/Domain/Billing/GenerateInvoiceButton.vue';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    companyId: number;
    monthlyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    availableYears: readonly number[];
    activeYear: number;
    /** Years with invoices still to generate, feeds the tab dot. */
    pendingInvoices?: App.Data.User.Billing.PendingInvoiceYearData[];
    companyRentalDiscounts?: App.Data.User.RentalDiscount.RentalDiscountListItemData[];
}>();

const yearsWithTodoSet = computed<Set<number>>(
    () => new Set(props.pendingInvoices?.map((p) => p.fiscalYear) ?? []),
);

const yearsDescending = computed<readonly number[]>(
    () => [...props.availableYears].sort((a, b) => b - a),
);

// Current year/month derived client-side, used only to hide the
// "Generate" branch on the running month and futures. The actual guard
// against generation lives backend-side.
const currentRealYear = computed<number>(() => new Date().getFullYear());
const currentRealMonth = computed<number>(() => new Date().getMonth() + 1);

const isCurrentYear = computed<boolean>(
    () => props.activeYear === currentRealYear.value,
);

const isFutureYear = computed<boolean>(
    () => props.activeYear > currentRealYear.value,
);

const statusLabel = computed<string>(() => {
    if (isFutureYear.value) {
        return 'Exercice à venir';
    }

    if (isCurrentYear.value) {
        return 'Exercice en cours';
    }

    return 'Exercice clos';
});

const statusDotClass = computed<string>(() => {
    if (isCurrentYear.value) {
        return 'bg-amber-500';
    }

    if (isFutureYear.value) {
        return 'bg-slate-400';
    }

    return 'bg-emerald-500';
});

const activeMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.daysUsed > 0).length,
);

const invoicedMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.existingInvoiceNumber !== null).length,
);

const blockedMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.hasMissingPricing).length,
);

// Months (1-12) eligible for invoice generation on the active year.
// Mirrors the backend `PendingInvoicesResolver`; the backend remains
// authoritative, this derivation is UI-only.
const pendingMonthsForActiveYear = computed<number[]>(() =>
    props.monthlyBilling.entries
        .filter(
            (e) =>
                e.daysUsed > 0
                && !e.hasMissingPricing
                && e.existingInvoiceId === null
                && (props.monthlyBilling.year < currentRealYear.value
                    || (props.monthlyBilling.year === currentRealYear.value
                        && e.month < currentRealMonth.value)),
        )
        .map((e) => e.month),
);

const activeMonthsPercent = computed<number>(() => {
    if (activeMonthsCount.value === 0) {
        return 0;
    }

    return Math.round((activeMonthsCount.value / 12) * 100);
});

const invoicedMonthsPercent = computed<number>(() => {
    if (activeMonthsCount.value === 0) {
        return 0;
    }

    return Math.round((invoicedMonthsCount.value / activeMonthsCount.value) * 100);
});

const metaLine = computed<string>(() => {
    const a = activeMonthsCount.value;
    const i = invoicedMonthsCount.value;
    const aPlural = a > 1 ? 's' : '';
    const iPlural = i > 1 ? 's' : '';

    if (a === 0) {
        return 'Aucune activité facturable sur l\'exercice.';
    }

    if (i === 0) {
        return `${a} mois actif${aPlural} · aucune annexe émise.`;
    }

    return `${a} mois actif${aPlural} · ${i} annexe${iPlural} émise${iPlural}.`;
});

const totalLabel = computed<string>(() => {
    if (props.monthlyBilling.yearTotalCents === null) {
        return '·';
    }

    return formatEur(props.monthlyBilling.yearTotalCents / 100, 2);
});

const totalDiscountLabel = computed<string>(() =>
    formatEur(props.monthlyBilling.yearTotalDiscountCentsPartial / 100, 2),
);

const grossTotalLabel = computed<string>(() =>
    formatEur(props.monthlyBilling.yearTotalGrossCentsPartial / 100, 2),
);

const hasAnyDiscount = computed<boolean>(
    () => props.monthlyBilling.yearTotalDiscountCentsPartial > 0,
);

const rentalDiscounts = computed<App.Data.User.RentalDiscount.RentalDiscountListItemData[]>(
    () => props.companyRentalDiscounts ?? [],
);

const statusToTone = {
    active: 'emerald',
    planned: 'amber',
    expired: 'slate',
} as const;

const statusLabelMap = {
    active: 'Active',
    planned: 'Planifiée',
    expired: 'Expirée',
} as const;

const totalCaption = computed<string>(() => {
    if (isFutureYear.value) {
        return `Total HT prévisionnel ${props.activeYear}`;
    }

    if (isCurrentYear.value) {
        return `Total HT facturable provisoire ${props.activeYear}`;
    }

    return `Total HT facturable ${props.activeYear}`;
});

const tabsState = injectCompanyTabsState();

function selectYear(year: number): void {
    if (year === props.activeYear) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('year', String(year));
    url.searchParams.set('tab', 'billing');
    url.searchParams.delete('periodStart');
    url.searchParams.delete('periodEnd');

    router.get(
        url.pathname + url.search,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['companyBilling', 'billingYear'],
            replace: true,
            onSuccess: () => {
                tabsState?.markStale(['fiscal', 'contracts']);
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Facturation · Exercice {{ activeYear }}
        </p>
        <div class="mb-1 flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                Facturation {{ activeYear }}
            </h2>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                <span
                    :class="['inline-block size-1.5 rounded-full', statusDotClass]"
                    aria-hidden="true"
                />
                {{ statusLabel }}
            </span>
        </div>
        <p class="mb-7 text-sm text-slate-500">
            {{ metaLine }}
        </p>

        <nav
            v-if="availableYears.length > 0"
            class="mb-10 flex gap-6 border-b border-slate-100"
            aria-label="Sélection de l'exercice"
        >
            <button
                v-for="year in yearsDescending"
                :key="year"
                type="button"
                :class="[
                    '-mb-px cursor-pointer border-b-2 pb-3 text-sm font-medium transition-colors duration-[120ms]',
                    activeYear === year
                        ? 'border-slate-900 text-slate-900'
                        : 'border-transparent text-slate-500 hover:text-slate-900',
                ]"
                @click="selectYear(year)"
            >
                {{ year }}
                <span
                    v-if="yearsWithTodoSet.has(year)"
                    class="ml-1 inline-block size-1 rounded-full bg-amber-500 align-middle"
                    title="Annexes à générer sur cet exercice"
                    aria-hidden="true"
                />
            </button>
        </nav>

        <div class="mb-10 flex flex-col gap-1.5">
            <p
                class="font-mono text-[28px] sm:text-[36px] font-medium tracking-[-0.02em] tabular-nums leading-none"
                :class="monthlyBilling.yearTotalCents === null ? 'text-slate-400' : 'text-slate-900'"
            >
                {{ totalLabel }}
            </p>
            <p class="text-sm text-slate-500">
                {{ totalCaption }}
            </p>
            <p
                v-if="hasAnyDiscount"
                class="mt-1 inline-flex items-center gap-2 text-xs text-slate-500"
            >
                <span>Brut <span class="font-mono tabular-nums text-slate-700">{{ grossTotalLabel }}</span></span>
                <span aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1 text-emerald-700">
                    <BadgePercent :size="13" :stroke-width="2" aria-hidden="true" />
                    Réductions appliquées <span class="font-mono tabular-nums">{{ totalDiscountLabel }}</span>
                </span>
            </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6">
            <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Jours utilisés
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ monthlyBilling.yearTotalDaysUsed }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    sur l'exercice
                </p>
            </div>
            <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Mois actifs
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ activeMonthsCount }} <span class="text-slate-400">/ 12</span>
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full bg-slate-800 transition-[width] duration-300"
                            :style="{ width: `${activeMonthsPercent}%` }"
                        />
                    </div>
                    <span class="font-mono text-[11px] tabular-nums text-slate-500">
                        {{ activeMonthsPercent }} %
                    </span>
                </div>
            </div>
            <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Annexes générées
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ invoicedMonthsCount }}
                    <span v-if="activeMonthsCount > 0" class="text-slate-400">/ {{ activeMonthsCount }}</span>
                </p>
                <div v-if="activeMonthsCount > 0" class="mt-2 flex items-center gap-2">
                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full bg-slate-800 transition-[width] duration-300"
                            :style="{ width: `${invoicedMonthsPercent}%` }"
                        />
                    </div>
                    <span class="font-mono text-[11px] tabular-nums text-slate-500">
                        {{ invoicedMonthsPercent }} %
                    </span>
                </div>
                <p v-else class="mt-1 text-[11px] text-slate-500">
                    aucun mois actif
                </p>
            </div>
            <div class="sm:px-6 sm:last:pr-0">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Tarifs manquants
                </p>
                <p
                    class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none"
                    :class="blockedMonthsCount > 0 ? 'text-amber-700' : 'text-slate-900'"
                >
                    {{ blockedMonthsCount }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    <template v-if="blockedMonthsCount > 0">
                        mois bloqué<template v-if="blockedMonthsCount > 1">s</template>
                    </template>
                    <template v-else>
                        tous les mois sont chiffrés
                    </template>
                </p>
            </div>
        </div>

        <section class="mt-12 flex flex-col gap-4">
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Mensualités
                </p>
                <BulkGenerateInvoicesButton
                    :company-id="companyId"
                    :year="monthlyBilling.year"
                    :pending-months="pendingMonthsForActiveYear"
                />
            </div>

            <BulkInvoiceGenerationReportAlert />

            <MonthlyBillingBreakdownCard
                :monthly-billing="monthlyBilling"
                unwrapped
            >
                <template #row-actions="{ entry }">
                    <GenerateInvoiceButton
                        :company-id="companyId"
                        :year="monthlyBilling.year"
                        :month="entry.month"
                        :current-real-year="currentRealYear"
                        :current-real-month="currentRealMonth"
                        :days-used="entry.daysUsed"
                        :has-missing-pricing="entry.hasMissingPricing"
                        :existing-invoice-id="entry.existingInvoiceId"
                        :existing-invoice-number="entry.existingInvoiceNumber"
                        :existing-invoice-total-cents="entry.invoicedTotalCents"
                        :existing-invoiced-days-used="entry.invoicedDaysUsed"
                        :current-total-cents="entry.totalCents"
                    />
                </template>
            </MonthlyBillingBreakdownCard>
        </section>

        <section
            v-if="rentalDiscounts.length > 0"
            class="mt-12 flex flex-col gap-4"
        >
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Réductions commerciales
                </p>
                <p class="text-xs text-slate-500">
                    {{ rentalDiscounts.length }} réduction{{ rentalDiscounts.length > 1 ? 's' : '' }} enregistrée{{ rentalDiscounts.length > 1 ? 's' : '' }}
                </p>
            </div>
            <ul class="divide-y divide-slate-100 border-y border-slate-100">
                <li
                    v-for="discount in rentalDiscounts"
                    :key="discount.id"
                    class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                    <div class="flex flex-1 flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <RentalDiscountPill
                                :basis-points="discount.discountBasisPoints"
                                :tone="statusToTone[discount.status as keyof typeof statusToTone]"
                                size="md"
                            />
                            <span class="text-sm font-medium text-slate-900">
                                {{ discount.label ?? 'Réduction sans libellé' }}
                            </span>
                            <span
                                class="text-[10px] font-semibold uppercase tracking-[0.08em]"
                                :class="{
                                    'text-emerald-700': discount.status === 'active',
                                    'text-amber-700': discount.status === 'planned',
                                    'text-slate-500': discount.status === 'expired',
                                }"
                            >
                                {{ statusLabelMap[discount.status as keyof typeof statusLabelMap] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Du {{ formatDateFr(discount.startDate) }} au {{ formatDateFr(discount.endDate) }}
                            <span aria-hidden="true">·</span>
                            <template v-if="discount.isAllVehicles">
                                tous les véhicules
                            </template>
                            <template v-else>
                                {{ discount.vehiclesCount }} véhicule{{ discount.vehiclesCount > 1 ? 's' : '' }} ciblé{{ discount.vehiclesCount > 1 ? 's' : '' }}
                            </template>
                        </p>
                    </div>
                </li>
            </ul>
            <p class="text-xs text-slate-400">
                Les réductions actives sont automatiquement appliquées au calcul de la facture mensuelle. Le détail brut / réduction / net apparaît sur les mois concernés ci-dessus.
            </p>
        </section>
    </div>
</template>

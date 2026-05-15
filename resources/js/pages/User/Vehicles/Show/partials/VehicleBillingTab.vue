<script setup lang="ts">
/**
 * Onglet Facturation de la fiche véhicule.
 *
 * Refonte design D5.10.W (direction « Linear-éditorial ») · structure
 * miroir des onglets Fiscalité / Facturation entreprise · header
 * éditorial sans Card wrapping, year tabs underline, hero recettes,
 * stats row, sections flush via `<MonthlyBillingBreakdownCard unwrapped>`
 * et `<VehiclePricingsCard unwrapped>`.
 *
 * Sections (de haut en bas) ·
 *   1. Header · eyebrow `FACTURATION · EXERCICE {Y}` + h2 `Recettes
 *      {Y}` + status dot inline + meta « N mois actifs · X jours
 *      utilisés ».
 *   2. Year tabs underline.
 *   3. Hero · total HT recettes en mono large, caption contextuelle.
 *   4. Stats row · 4 KPIs · Jours utilisés, Mois actifs (+ barre),
 *      Tarif jour actif, Tarifs manquants.
 *   5. Section `RECETTES MENSUELLES` · eyebrow + table mensuelle flush.
 *   6. Section `TARIFS ANNUELS` · eyebrow + table pricings flush
 *      (multi-années · indépendant du year tab).
 *
 * La génération de facture se fait depuis la fiche entreprise (cumul
 * cross-véhicules par mois) ou depuis la liste Factures · pas
 * d'action de génération sur cette page.
 *
 * D5.10.U · sélecteur d'année piloté par le param URL unifié `?year=`
 * partagé avec l'onglet Fiscalité.
 */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import { injectVehicleTabsState } from '@/Composables/Vehicle/Show/useVehicleTabs';
import { formatEur } from '@/Utils/format/formatEur';
import VehiclePricingsCard from './billing/VehiclePricingsCard.vue';

const props = defineProps<{
    vehicleId: number;
    pricings: ReadonlyArray<App.Data.User.Vehicle.VehicleYearlyPricingData>;
    monthlyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    /**
     * Scope global d'années (cf. `YearScopeData`). On utilise
     * `availableYears` pour les tabs underline et `currentYear` pour
     * le status dot inline.
     */
    yearScope: App.Data.Shared.YearScopeData;
    activeYear: number;
}>();

const yearsDescending = computed<readonly number[]>(
    () => [...props.yearScope.availableYears].sort((a, b) => b - a),
);

const isCurrentYear = computed<boolean>(
    () => props.activeYear === props.yearScope.currentYear,
);

const isFutureYear = computed<boolean>(
    () => props.activeYear > props.yearScope.currentYear,
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

const activeMonthsPercent = computed<number>(() => {
    if (activeMonthsCount.value === 0) {
        return 0;
    }

    return Math.round((activeMonthsCount.value / 12) * 100);
});

const blockedMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.hasMissingPricing).length,
);

/**
 * Tarif jour applicable à l'année active · si un pricing existe pour
 * cette année, on l'expose en KPI. Sinon `null` et le KPI affiche
 * « Non renseigné ».
 */
const activePricing = computed<App.Data.User.Vehicle.VehicleYearlyPricingData | null>(
    () => props.pricings.find((p) => p.year === props.activeYear) ?? null,
);

const totalLabel = computed<string>(() => {
    if (props.monthlyBilling.yearTotalCents === null) {
        return '·';
    }

    return formatEur(props.monthlyBilling.yearTotalCents / 100, 2);
});

const totalCaption = computed<string>(() => {
    if (isFutureYear.value) {
        return `Recettes prévisionnelles · ${props.activeYear}`;
    }

    if (isCurrentYear.value) {
        return `Recettes HT cross-entreprises · provisoire ${props.activeYear}`;
    }

    return `Recettes HT cross-entreprises · ${props.activeYear}`;
});

const metaLine = computed<string>(() => {
    const m = activeMonthsCount.value;
    const d = props.monthlyBilling.yearTotalDaysUsed;
    const mPlural = m > 1 ? 's' : '';
    const dPlural = d > 1 ? 's' : '';

    if (m === 0) {
        return 'Aucune activité facturable sur l\'exercice.';
    }

    return `${m} mois actif${mPlural} · ${d} jour${dPlural} utilisé${dPlural}.`;
});

const tabsState = injectVehicleTabsState();

function selectYear(year: number): void {
    if (year === props.activeYear) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('year', String(year));
    url.searchParams.set('tab', 'billing');

    router.get(
        url.pathname + url.search,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['vehicleBilling', 'billingYear', 'fiscalYear'],
            replace: true,
            onSuccess: () => {
                tabsState?.markStale(['fiscal']);
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col">
        <!-- Header éditorial · status dot stack sous le titre sur mobile -->
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Facturation · Exercice {{ activeYear }}
        </p>
        <div class="mb-1 flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                Recettes {{ activeYear }}
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

        <!-- Year tabs underline -->
        <nav
            v-if="yearScope.availableYears.length > 0"
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
            </button>
        </nav>

        <!-- Hero · total recettes -->
        <div class="mb-10 flex flex-col gap-1.5">
            <p
                class="font-mono text-[36px] sm:text-[44px] font-medium tracking-[-0.02em] tabular-nums leading-none"
                :class="monthlyBilling.yearTotalCents === null ? 'text-slate-400' : 'text-slate-900'"
            >
                {{ totalLabel }}
            </p>
            <p class="text-sm text-slate-500">
                {{ totalCaption }}
            </p>
        </div>

        <!--
            Stats row · 4 colonnes sur ≥ sm, 2×2 sur mobile · gap
            horizontal entre cards mobile, bordures verticales sur sm+.
        -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6">
            <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Jours utilisés
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ monthlyBilling.yearTotalDaysUsed }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    cross-entreprises
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
                    Tarif jour
                </p>
                <p
                    v-if="activePricing"
                    class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900"
                >
                    {{ formatEur(activePricing.dailyRateCents / 100, 0) }}
                </p>
                <p
                    v-else
                    class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-400"
                >
                    ·
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    <template v-if="activePricing">
                        appliqué sur {{ activeYear }}
                    </template>
                    <template v-else>
                        non renseigné
                    </template>
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

        <!-- Section · Recettes mensuelles -->
        <section class="mt-12 flex flex-col gap-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Recettes mensuelles
            </p>
            <MonthlyBillingBreakdownCard
                :monthly-billing="monthlyBilling"
                :show-invoice-number-column="false"
                unwrapped
            />
        </section>

        <!--
            Section · Tarifs annuels (multi-années).
            Indépendant du year tab · liste toutes les années de
            référence de tarification du véhicule, avec actions
            d'édition / suppression et bouton Ajouter.
        -->
        <section class="mt-12 flex flex-col gap-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Tarifs annuels
            </p>
            <VehiclePricingsCard
                :vehicle-id="props.vehicleId"
                :pricings="props.pricings"
                unwrapped
            />
        </section>
    </div>
</template>

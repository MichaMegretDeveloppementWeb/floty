<script setup lang="ts">
/**
 * Onglet « Facturation » de la page Show Company.
 *
 * Refonte design D5.10.W · alignement sur l'onglet Fiscalité refondu
 * (direction « Linear-éditorial ») · header purement typographique,
 * year tabs underline, hero KPI mono, stats row, table flush sans
 * Card wrapping (cf. prop `unwrapped` sur `MonthlyBillingBreakdownCard`).
 *
 * Sections ·
 *   1. Header éditorial · eyebrow `FACTURATION · EXERCICE {Y}` + h2
 *      `Facturation {Y}` + status dot inline (clos / en cours / à
 *      venir) + meta (« 5 / 12 mois facturables » etc.).
 *   2. Year tabs underline.
 *   3. Hero · total HT facturable en mono `text-[44px]`.
 *   4. Stats row · 4 KPIs (jours utilisés, mois actifs avec mini-bar,
 *      factures émises avec mini-bar, mois bloqués si tarif manquant).
 *   5. Section `MENSUALITÉS` · eyebrow + table mensuelle flush
 *      (`MonthlyBillingBreakdownCard` en mode `unwrapped`).
 */
import { router } from '@inertiajs/vue3';
import { BadgePercent } from 'lucide-vue-next';
import { computed } from 'vue';
import GenerateInvoiceButton from '@/Components/Domain/Billing/GenerateInvoiceButton.vue';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    companyId: number;
    monthlyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    /**
     * Plage continue [firstYear..currentYear] partagée avec l'onglet
     * Contrats · cohérence UX · on ne propose que les années où
     * l'entreprise a un contrat plausible.
     */
    availableYears: readonly number[];
    activeYear: number;
    /** Années avec factures à générer · alimente le dot des tabs. */
    pendingInvoices?: App.Data.User.Billing.PendingInvoiceYearData[];
    /**
     * Réductions commerciales de l'entreprise (Lot 3 chantier
     * RentalDiscount) · alimente la section dédiée affichée sous le
     * récap mensuel. Liste vide = pas de réduction enregistrée. Servie
     * en eager par {@see CompanyController::show()} quand l'onglet
     * Facturation est actif.
     */
    companyRentalDiscounts?: App.Data.User.RentalDiscount.RentalDiscountListItemData[];
}>();

const yearsWithTodoSet = computed<Set<number>>(
    () => new Set(props.pendingInvoices?.map((p) => p.fiscalYear) ?? []),
);

const yearsDescending = computed<readonly number[]>(
    () => [...props.availableYears].sort((a, b) => b - a),
);

/**
 * Année et mois courants calculés côté client (le DTO n'expose pas de
 * `currentRealYear` / `currentRealMonth` pour Billing, donc on s'appuie
 * sur la date du client · acceptable car la sémantique est juste
 * visuelle, le guard de génération facture reste backend).
 *
 * `currentRealMonth` est utilisé par `GenerateInvoiceButton` pour
 * masquer la branche « Générer » sur le mois en cours et les mois
 * futurs (une facture ne se génère qu'à mois écoulé).
 */
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

/**
 * Décompte des mois avec activité (jours utilisés > 0) · sert au KPI
 * « Mois actifs » et à la barre de progression d'avancement.
 */
const activeMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.daysUsed > 0).length,
);

/**
 * Décompte des mois déjà couverts par une facture · sert au KPI
 * « Factures émises » et à la barre de progression de facturation.
 */
const invoicedMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.existingInvoiceNumber !== null).length,
);

/**
 * Décompte des mois bloqués par un tarif annuel manquant · KPI
 * exposé uniquement si > 0, sinon la colonne disparaît.
 */
const blockedMonthsCount = computed<number>(
    () => props.monthlyBilling.entries.filter((e) => e.hasMissingPricing).length,
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

/**
 * Lot 3 réductions commerciales · expose le total des réductions
 * appliquées sur l'exercice. Mise en avant comme stat sous le hero
 * uniquement si > 0 (sinon la cellule reste sobre, pas de bruit
 * visuel sur les exercices sans réduction).
 */
const totalDiscountLabel = computed<string>(() =>
    formatEur(props.monthlyBilling.yearTotalDiscountCentsPartial / 100, 2),
);

const grossTotalLabel = computed<string>(() =>
    formatEur(props.monthlyBilling.yearTotalGrossCentsPartial / 100, 2),
);

const hasAnyDiscount = computed<boolean>(
    () => props.monthlyBilling.yearTotalDiscountCentsPartial > 0,
);

/**
 * Toutes les réductions enregistrées sur l'entreprise (active /
 * planifiée / expirée), tri pre-trié backend par `start_date` DESC.
 * Vide tant que le onglet Facturation n'a jamais été visité (prop
 * `Inertia::optional`).
 */
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
        <!-- Header éditorial · status dot stack sous le titre sur mobile -->
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

        <!-- Year tabs underline -->
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

        <!-- Hero · total HT -->
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
            <!-- Détail brut / réduction visible uniquement si au moins
                 une réduction a été appliquée sur l'exercice ·
                 transparent pour les entreprises sans réduction. -->
            <p
                v-if="hasAnyDiscount"
                class="mt-1 inline-flex items-center gap-2 text-xs text-slate-500"
            >
                <span>Brut <span class="font-mono tabular-nums text-slate-700">{{ grossTotalLabel }}</span></span>
                <span aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1 text-emerald-700">
                    <BadgePercent :size="13" :stroke-width="2" aria-hidden="true" />
                    Économie réalisée <span class="font-mono tabular-nums">{{ totalDiscountLabel }}</span>
                </span>
            </p>
        </div>

        <!--
            Stats row · 4 colonnes sur ≥ sm, 2×2 sur mobile. Sur
            mobile · `gap-x-5 gap-y-6` pour aérer entre cards et
            entre rangs, pas de bordures. Sur sm+ · gap réinitialisé,
            espacement porté par `sm:px-6` + séparateurs verticaux
            `sm:not-last:border-r`.
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

        <!-- Section · table mensuelle flush -->
        <section class="mt-12 flex flex-col gap-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Mensualités
            </p>

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

        <!-- Section · réductions commerciales de l'entreprise (Lot 3).
             Affichée uniquement si la liste n'est pas vide · sinon
             aucun bruit visuel pour les entreprises sans réduction. -->
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

<script setup lang="ts">
/**
 * Fiscal tab on the Company detail page: editorial header, year tabs,
 * total hero, KPI stats row and the declaration section (prose for the
 * untouched state, DeclarationStateCard for the others).
 */
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, FileText, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DeclarationStateCard from '@/Components/Domain/Declaration/DeclarationStateCard.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useCompanyFiscalSelectedYear } from '@/Composables/Company/Show/useCompanyFiscalSelectedYear';
import { show as companyShow } from '@/routes/user/companies';
import { prepare as prepareDeclarationRoute } from '@/routes/user/declarations';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
    declarationLifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
    /** Years with pending declarations, feeds the amber dot on year tabs. */
    pendingDeclarations?: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
}>();

const { selectedYear, selectYear, loading: yearLoading } = useCompanyFiscalSelectedYear(
    props.fiscal.year,
);

// A year is declarable once it is over (CIBS, declared in January N+1).
const isDeclarable = computed<boolean>(
    () => selectedYear.value < props.fiscal.currentRealYear,
);

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.fiscal.currentRealYear,
);

const isFutureYear = computed<boolean>(
    () => selectedYear.value > props.fiscal.currentRealYear,
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

const metaLine = computed<string>(() => {
    const nextYear = selectedYear.value + 1;

    if (isFutureYear.value) {
        return `Déclarable en janvier ${nextYear} · annexe 3310-A (régime réel) ou formulaire 3517 (régime simplifié).`;
    }

    if (isCurrentYear.value) {
        return `Déclarable en janvier ${nextYear} · annexe 3310-A (régime réel) ou formulaire 3517 (régime simplifié).`;
    }

    return `Déclarable depuis janvier ${nextYear} · annexe 3310-A (régime réel) ou formulaire 3517 (régime simplifié).`;
});

const yearsWithTodoSet = computed<Set<number>>(
    () => new Set(props.pendingDeclarations?.map((d) => d.fiscalYear) ?? []),
);

const yearsDescending = computed<readonly number[]>(
    () => [...props.fiscal.availableYears].sort((a, b) => b - a),
);

const hasActivity = computed<boolean>(
    () => props.fiscal.totalDays > 0 || props.fiscal.contractsCount > 0,
);

const hasTax = computed<boolean>(() => props.fiscal.totalTaxAll > 0);

const co2Percent = computed<number>(() => {
    if (props.fiscal.totalTaxAll <= 0) {
        return 0;
    }

    return Math.round((props.fiscal.totalTaxCo2 / props.fiscal.totalTaxAll) * 100);
});

const pollutantsPercent = computed<number>(() => 100 - co2Percent.value);

const vehiclesCount = computed<number>(() => props.fiscal.rows.length);

const locationsHref = computed<string>(() => {
    const year = props.fiscal.year;

    return companyShow(props.companyId, {
        query: {
            tab: 'contracts',
            periodStart: `${year}-01-01`,
            periodEnd: `${year}-12-31`,
        },
    }).url;
});

const isUntouched = computed<boolean>(
    () => props.declarationLifecycle.state === 'untouched',
);

const preparing = ref<boolean>(false);

function prepareDeclaration(): void {
    if (preparing.value) {
        return;
    }

    preparing.value = true;
    router.post(
        prepareDeclarationRoute.url(),
        {
            company_id: props.companyId,
            fiscal_year: selectedYear.value,
        },
        {
            preserveScroll: false,
            onFinish: () => {
                preparing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Fiscalité · Exercice {{ selectedYear }}
        </p>
        <div class="mb-1 flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                Taxes {{ selectedYear }}
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
            v-if="props.fiscal.availableYears.length > 0"
            class="mb-10 flex gap-6 border-b border-slate-100"
            aria-label="Sélection de l'exercice"
        >
            <button
                v-for="year in yearsDescending"
                :key="year"
                type="button"
                :disabled="yearLoading"
                :class="[
                    '-mb-px cursor-pointer border-b-2 pb-3 text-sm font-medium transition-colors duration-[120ms]',
                    selectedYear === year
                        ? 'border-slate-900 text-slate-900'
                        : 'border-transparent text-slate-500 hover:text-slate-900',
                    yearLoading && 'cursor-wait opacity-60',
                ]"
                @click="selectYear(year)"
            >
                {{ year }}
                <span
                    v-if="yearsWithTodoSet.has(year)"
                    class="ml-1 inline-block size-1 rounded-full bg-amber-500 align-middle"
                    title="Action en attente sur cet exercice"
                    aria-hidden="true"
                />
            </button>
        </nav>

        <div class="mb-10 flex flex-col gap-1.5">
            <p class="font-mono text-[28px] sm:text-[36px] font-medium tracking-[-0.02em] tabular-nums leading-none text-slate-900">
                {{ formatEur(fiscal.totalTaxAll) }}
            </p>
            <p class="text-sm text-slate-500">
                <template v-if="isCurrentYear">Total provisoire</template>
                <template v-else-if="isFutureYear">Total prévisionnel</template>
                <template v-else>Total {{ fiscal.year }}</template>
            </p>
        </div>

        <div
            v-if="hasActivity"
            class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6"
        >
            <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Taxe CO₂
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscal.totalTaxCo2) }}
                </p>
                <div v-if="hasTax" class="mt-2 flex items-center gap-2">
                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full bg-slate-800 transition-[width] duration-300"
                            :style="{ width: `${co2Percent}%` }"
                        />
                    </div>
                    <span class="font-mono text-[11px] tabular-nums text-slate-500">
                        {{ co2Percent }} %
                    </span>
                </div>
            </div>
            <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Taxe polluants
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscal.totalTaxPollutants) }}
                </p>
                <div v-if="hasTax" class="mt-2 flex items-center gap-2">
                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full bg-slate-800 transition-[width] duration-300"
                            :style="{ width: `${pollutantsPercent}%` }"
                        />
                    </div>
                    <span class="font-mono text-[11px] tabular-nums text-slate-500">
                        {{ pollutantsPercent }} %
                    </span>
                </div>
            </div>
            <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Jours cumulés
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ fiscal.totalDays }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    sur {{ vehiclesCount }} véhicule<template v-if="vehiclesCount > 1">s</template>
                </p>
            </div>
            <div class="sm:px-6 sm:last:pr-0">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Locations
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ fiscal.contractsCount }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    contrats actifs
                </p>
            </div>
        </div>

        <p
            v-else
            class="border-y border-slate-100 py-8 text-center text-sm text-slate-500"
        >
            Aucune activité fiscale sur l'exercice {{ fiscal.year }}.
        </p>

        <Link
            v-if="hasActivity"
            :href="locationsHref"
            class="mt-4 inline-flex w-fit cursor-pointer items-center gap-1 text-sm text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
        >
            Voir les locations {{ fiscal.year }}
            <ArrowRight :size="14" :stroke-width="1.75" />
        </Link>

        <div class="mt-12">
            <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Déclaration
            </p>

            <div v-if="isUntouched" class="max-w-2xl text-[15px] leading-relaxed text-slate-700">
                <template v-if="isDeclarable">
                    <p>
                        Aucune déclaration n'a encore été préparée pour l'exercice
                        <strong class="font-medium text-slate-900">{{ selectedYear }}</strong>.
                        L'écran de revue permet de trancher les éventuels clusters de
                        risque (chaînes LCD requalifiables en LLD) avant la génération
                        du document définitif, à déposer via l'annexe
                        <span class="font-mono">3310-A</span> ou le formulaire
                        <span class="font-mono">3517</span>.
                    </p>
                    <div class="mt-5">
                        <Button :disabled="preparing" @click="prepareDeclaration">
                            <LoaderCircle v-if="preparing" :size="16" :stroke-width="1.75" class="animate-spin" />
                            <FileText v-else :size="16" :stroke-width="1.75" />
                            {{ preparing ? 'Préparation…' : 'Préparer la déclaration' }}
                        </Button>
                    </div>
                </template>
                <template v-else>
                    <p>
                        La déclaration au titre de l'exercice
                        <strong class="font-medium text-slate-900">{{ selectedYear }}</strong>
                        sera ouverte en
                        <strong class="font-medium text-slate-900">
                            janvier {{ selectedYear + 1 }}</strong>.
                    </p>
                </template>
            </div>

            <DeclarationStateCard
                v-else
                :lifecycle="props.declarationLifecycle"
                :company-id="props.companyId"
                :fiscal-year="props.fiscal.year"
                :is-declarable="isDeclarable"
            />
        </div>
    </div>
</template>

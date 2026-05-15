<script setup lang="ts">
/**
 * Onglet « Fiscalité » de la page Show Company.
 *
 * Refonte design D5.10.W (direction « Linear-éditorial ») · hiérarchie
 * purement typographique, zéro card sur le tab principal (les cards
 * subsistent uniquement pour les états déclaration S2-S7 qui ont des
 * contenus structurés · timeline, alerte). Eyebrow + titre + statut
 * inline + meta · pattern des dashboards Linear / Vercel.
 *
 * Sections (de haut en bas) ·
 *   1. Header éditorial · eyebrow `FISCALITÉ · EXERCICE {Y}` + h1
 *      `Taxes {Y}` + status dot inline + meta deadline déclaration.
 *   2. Year tabs underline (style onglets natifs · pas de pills).
 *   3. Hero · total provisoire en mono large (28px font-medium).
 *   4. Stats row · 4 colonnes (CO₂, Polluants, Jours, Locations)
 *      séparées par hairlines verticaux. Empty state remplace la
 *      row si aucune activité.
 *   5. Section « Déclaration » · prose éditoriale pour S1 (untouched),
 *      ou `<DeclarationStateCard>` pour les autres états.
 *
 * Le sélecteur d'année utilise le param URL **unifié** `?year=`
 * (D5.10.U) partagé entre les onglets Fiscalité / Facturation /
 * Activité.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import DeclarationStateCard from '@/Components/Domain/Declaration/DeclarationStateCard.vue';
import { useCompanyFiscalSelectedYear } from '@/Composables/Company/Show/useCompanyFiscalSelectedYear';
import { show as companyShow } from '@/routes/user/companies';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
    declarationLifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
    /**
     * D5.10.U · années avec déclarations en attente · alimente le dot
     * ambre sur les tabs d'année.
     */
    pendingDeclarations?: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
}>();

const { selectedYear, selectYear, loading: yearLoading } = useCompanyFiscalSelectedYear(
    props.fiscal.year,
);

/**
 * Une année est « déclarable » dès qu'elle est terminée · l'exercice
 * en cours et les exercices futurs ne le sont pas (CIBS · déclaration
 * en janvier N+1).
 */
const isDeclarable = computed<boolean>(
    () => selectedYear.value < props.fiscal.currentRealYear,
);

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.fiscal.currentRealYear,
);

const isFutureYear = computed<boolean>(
    () => selectedYear.value > props.fiscal.currentRealYear,
);

/**
 * Status compact affiché en ligne avec le titre · point coloré + texte.
 * Plus discret qu'un pill, plus lisible qu'une icône seule.
 */
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

/**
 * Années triées du plus récent au plus ancien (consultation
 * la plus probable à gauche).
 */
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

/**
 * S1 (untouched) est rendu en prose éditoriale sous le `section-h`
 * "Déclaration". Les autres états (S2 à S7) déléguent à
 * `<DeclarationStateCard>` qui gère leur card structurée.
 */
const isUntouched = computed<boolean>(
    () => props.declarationLifecycle.state === 'untouched',
);
</script>

<template>
    <div class="flex flex-col">
        <!-- Header éditorial · pattern eyebrow + title + meta -->
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
            Fiscalité · Exercice {{ selectedYear }}
        </p>
        <div class="mb-1 flex items-baseline justify-between gap-6">
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

        <!-- Year tabs · underline style (pas de pills) -->
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

        <!-- Hero · total provisoire -->
        <div class="mb-10 flex flex-col gap-1.5">
            <p class="font-mono text-[44px] font-medium tracking-[-0.02em] tabular-nums leading-none text-slate-900">
                {{ formatEur(fiscal.totalTaxAll) }}
            </p>
            <p class="text-sm text-slate-500">
                <template v-if="isCurrentYear">Total provisoire</template>
                <template v-else-if="isFutureYear">Total prévisionnel</template>
                <template v-else>Total {{ fiscal.year }}</template>
            </p>
        </div>

        <!-- Stats row · 4 colonnes séparées par hairlines verticaux -->
        <div
            v-if="hasActivity"
            class="grid grid-cols-4 border-y border-slate-100 py-6"
        >
            <div class="px-6 first:pl-0 last:pr-0 not-last:border-r border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Taxe CO₂
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscal.totalTaxCo2) }}
                </p>
                <p v-if="hasTax" class="mt-1 font-mono text-[11px] tabular-nums text-slate-500">
                    {{ co2Percent }} %
                </p>
            </div>
            <div class="px-6 not-last:border-r border-slate-100">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Taxe polluants
                </p>
                <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscal.totalTaxPollutants) }}
                </p>
                <p v-if="hasTax" class="mt-1 font-mono text-[11px] tabular-nums text-slate-500">
                    {{ pollutantsPercent }} %
                </p>
            </div>
            <div class="px-6 not-last:border-r border-slate-100">
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
            <div class="px-6 last:pr-0">
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

        <!-- Lien locations -->
        <Link
            v-if="hasActivity"
            :href="locationsHref"
            class="mt-4 inline-flex w-fit cursor-pointer items-center gap-1 text-sm text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
        >
            Voir les locations {{ fiscal.year }}
            <ArrowRight :size="14" :stroke-width="1.75" />
        </Link>

        <!-- Section Déclaration -->
        <div class="mt-12">
            <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Déclaration
            </p>

            <!--
                S1 (untouched) · rendu en prose éditoriale dans le flux
                du tab. Les autres états (S2 à S7) déléguent à
                <DeclarationStateCard> qui gère leur propre layout
                éditorial (status dot + prose + actions).
            -->
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
                </template>
                <template v-else>
                    <p>
                        La déclaration au titre de l'exercice
                        <strong class="font-medium text-slate-900">{{ selectedYear }}</strong>
                        sera ouverte en
                        <strong class="font-medium text-slate-900">
                            janvier {{ selectedYear + 1 }}</strong>.
                        Elle se dépose via l'annexe
                        <span class="font-mono">3310-A</span> jointe à la déclaration
                        de TVA (régime réel) ou via le formulaire
                        <span class="font-mono">3517</span> (régime simplifié).
                        Les chiffres ci-dessus évolueront jusqu'à la clôture de
                        l'année.
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

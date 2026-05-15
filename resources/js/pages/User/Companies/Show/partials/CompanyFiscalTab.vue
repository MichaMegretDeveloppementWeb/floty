<script setup lang="ts">
/**
 * Onglet « Fiscalité » de la page Show Company.
 *
 * Refonte design D5.10.W · structure éditoriale alignée sur le design
 * system Floty (eyebrow + titre + meta, hiérarchie verticale claire,
 * surfaces aérées, pas de Card imbriquée pour le header).
 *
 * Sections (de haut en bas) ·
 *   1. Header éditorial · eyebrow `FISCALITÉ` + titre + meta exercice
 *      + year pills sur la même bande.
 *   2. `<CompanyFiscalRecapCard>` · hero du montant total + breakdown
 *      CO2/Polluants + KPIs activité.
 *   3. `<DeclarationStateCard>` · cycle de vie déclaration. Pour les
 *      années non déclarables (en cours / à venir), c'est un bandeau
 *      info léger qui rappelle la deadline janvier N+1 plutôt qu'une
 *      carte action.
 *
 * Le sélecteur d'année utilise le param URL **unifié** `?year=`
 * (D5.10.U) partagé entre les onglets Fiscalité / Facturation /
 * Activité de la fiche entreprise.
 */
import { computed } from 'vue';
import DeclarationStateCard from '@/Components/Domain/Declaration/DeclarationStateCard.vue';
import YearPills from '@/Components/Ui/YearPills/YearPills.vue';
import { useCompanyFiscalSelectedYear } from '@/Composables/Company/Show/useCompanyFiscalSelectedYear';
import CompanyFiscalRecapCard from './CompanyFiscalRecapCard.vue';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
    declarationLifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
    /**
     * D5.10.U · années avec déclarations en attente · alimente le dot
     * ambre sur les pills d'année pour guider l'utilisateur vers
     * l'exercice concerné.
     */
    pendingDeclarations?: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
}>();

const { selectedYear, selectYear, loading: yearLoading } = useCompanyFiscalSelectedYear(
    props.fiscal.year,
);

/**
 * Une année est « déclarable » dès qu'elle est terminée · l'exercice
 * en cours et les exercices futurs ne le sont pas (CIBS · déclaration
 * en janvier N+1 via annexe 3310-A ou formulaire 3517).
 */
const isDeclarable = computed<boolean>(
    () => selectedYear.value < props.fiscal.currentRealYear,
);

const exerciseStatus = computed<{ label: string; tone: 'amber' | 'slate' | 'blue' }>(() => {
    if (selectedYear.value > props.fiscal.currentRealYear) {
        return { label: 'À venir', tone: 'slate' };
    }
    if (selectedYear.value === props.fiscal.currentRealYear) {
        return { label: 'En cours', tone: 'amber' };
    }

    return { label: 'Clos', tone: 'blue' };
});

const exerciseMeta = computed<string>(() => {
    if (selectedYear.value > props.fiscal.currentRealYear) {
        return `Déclarable en janvier ${selectedYear.value + 1}`;
    }
    if (selectedYear.value === props.fiscal.currentRealYear) {
        return `Chiffres provisoires · déclarable en janvier ${selectedYear.value + 1}`;
    }

    return `Déclarable depuis janvier ${selectedYear.value + 1}`;
});

const yearsWithTodo = computed<readonly number[]>(
    () => props.pendingDeclarations?.map((d) => d.fiscalYear) ?? [],
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!--
            Header éditorial · pattern « eyebrow + title + meta »
            (design-system README · CONTENT FUNDAMENTALS). Pas de Card
            wrapping pour éviter le ton « formulaire ».
        -->
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5">
            <div class="flex flex-col gap-1.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Fiscalité · Exercice {{ selectedYear }}
                </p>
                <div class="flex flex-wrap items-baseline gap-3">
                    <h2 class="text-2xl font-semibold leading-none tracking-tight text-slate-900">
                        Taxes annuelles
                    </h2>
                    <span
                        :class="[
                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            exerciseStatus.tone === 'amber' && 'bg-amber-50 text-amber-700',
                            exerciseStatus.tone === 'blue' && 'bg-blue-50 text-blue-700',
                            exerciseStatus.tone === 'slate' && 'bg-slate-100 text-slate-600',
                        ]"
                    >
                        {{ exerciseStatus.label }}
                    </span>
                </div>
                <p class="text-sm text-slate-500">
                    {{ exerciseMeta }}
                </p>
            </div>

            <YearPills
                v-if="props.fiscal.availableYears.length > 0"
                :years="props.fiscal.availableYears"
                :active-year="selectedYear"
                :years-with-todo="yearsWithTodo"
                :loading="yearLoading"
                @select="selectYear"
            />
        </header>

        <CompanyFiscalRecapCard
            :fiscal="props.fiscal"
            :company-id="props.companyId"
        />

        <DeclarationStateCard
            :lifecycle="props.declarationLifecycle"
            :company-id="props.companyId"
            :fiscal-year="props.fiscal.year"
            :is-declarable="isDeclarable"
        />
    </div>
</template>

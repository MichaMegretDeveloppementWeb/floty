<script setup lang="ts">
/**
 * Onglet « Fiscalité » de la page Show Company (chantier N.2).
 *
 * UX cohérente avec l'onglet Contrats :
 * - Stats contextuelles sous le titre (jours cumulés + véhicules
 *   taxés + total CO₂/polluants/combiné), reflètent l'année active.
 * - Pills d'années cliquables (1 clic = exercice complet) — scalable
 *   sur 20+ années via scroll horizontal.
 * - Pas de « période personnalisée » : la fiscalité raisonne
 *   strictement sur des exercices entiers.
 *
 * Le sélecteur d'année est **local et indépendant** (ADR-0020 D3).
 * Aucun lien avec le sélecteur global, ni avec celui de l'onglet
 * Contrats — chaque section a sa propre vie.
 */
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { useCompanyFiscalSelectedYear } from '@/Composables/Company/Show/useCompanyFiscalSelectedYear';
import { formatEur } from '@/Utils/format/formatEur';
import CompanyFiscalBreakdownTable from './CompanyFiscalBreakdownTable.vue';
import CompanyYearPills from './CompanyYearPills.vue';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
}>();

const { selectedYear, selectYear } = useCompanyFiscalSelectedYear(
    props.fiscal.year,
);

const hasRows = computed<boolean>(() => props.fiscal.rows.length > 0);

const vehiclesCountLabel = computed<string>(() => {
    const count = props.fiscal.rows.length;
    return `${count} véhicule${count > 1 ? 's' : ''} taxé${count > 1 ? 's' : ''}`;
});

const totalDaysLabel = computed<string>(() => {
    const days = props.fiscal.totalDays;
    return `${days} jour${days > 1 ? 's' : ''} cumulé${days > 1 ? 's' : ''}`;
});

const isCurrentYear = computed<boolean>(
    () => selectedYear.value === props.fiscal.currentRealYear,
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Header : titre + stats contextuelles + pills années -->
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-base font-semibold text-slate-900">
                        Fiscalité
                        <span
                            v-if="isCurrentYear"
                            class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-700 uppercase"
                            title="Exercice fiscal en cours — chiffres provisoires"
                        >
                            En cours
                        </span>
                    </h3>
                    <p class="text-sm text-slate-500">
                        Exercice {{ props.fiscal.year }}
                        <template v-if="hasRows">
                            <span class="mx-1.5 text-slate-300">·</span>
                            <span>{{ vehiclesCountLabel }}</span>
                            <span class="mx-1.5 text-slate-300">·</span>
                            <span>{{ totalDaysLabel }}</span>
                            <span class="mx-1.5 text-slate-300">·</span>
                            <span>
                                CO₂ {{ formatEur(props.fiscal.totalTaxCo2) }} /
                                Polluants
                                {{ formatEur(props.fiscal.totalTaxPollutants) }}
                            </span>
                        </template>
                        <template v-else>
                            <span class="mx-1.5 text-slate-300">·</span>
                            <span>aucun véhicule taxé</span>
                        </template>
                    </p>
                </div>

                <CompanyYearPills
                    v-if="props.fiscal.availableYears.length > 0"
                    :years="props.fiscal.availableYears"
                    :active-year="selectedYear"
                    @select="selectYear"
                />
            </div>
        </Card>

        <!-- Empty state local : aucun véhicule taxé sur l'année sélectionnée -->
        <Card v-if="!hasRows">
            <p class="text-sm text-slate-500">
                Aucun véhicule taxé sur l'exercice {{ props.fiscal.year }}
                pour cette entreprise. Choisissez une autre année dans les
                pills ci-dessus pour voir les exercices passés.
            </p>
        </Card>

        <!-- Total combiné en gros + table breakdown -->
        <template v-else>
            <Card>
                <div class="flex flex-col gap-1">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Total taxes {{ props.fiscal.year }}
                    </p>
                    <p class="font-mono text-3xl font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(props.fiscal.totalTaxAll) }}
                    </p>
                    <p class="text-xs text-slate-500">
                        CO₂ {{ formatEur(props.fiscal.totalTaxCo2) }}
                        <span class="mx-1 text-slate-300">+</span>
                        Polluants {{ formatEur(props.fiscal.totalTaxPollutants) }}
                    </p>
                </div>
            </Card>

            <CompanyFiscalBreakdownTable :fiscal="props.fiscal" />
        </template>
    </div>
</template>

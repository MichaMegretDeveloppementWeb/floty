<script setup lang="ts">
/**
 * Carte recap fiscale d'une entreprise pour l'exercice sélectionné
 * (Phase 12 D5.9.A, aération D5.10.B). Présente :
 *   - Le total des taxes en avant (text-4xl).
 *   - **Balance CO₂ vs Polluants** rendue en 2 colonnes côte à côte
 *     avec barres proportionnelles · permet à l'utilisateur de
 *     comprendre d'un coup d'œil quelle taxe domine.
 *   - 3 stats aérées (jours cumulés, véhicules taxés, locations
 *     couvrant l'exercice).
 *   - Un lien contextuel vers l'onglet Locations filtré sur la
 *     période de l'année.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
}>();

const hasActivity = computed<boolean>(
    () => props.fiscal.totalDays > 0 || props.fiscal.contractsCount > 0,
);

const hasTax = computed<boolean>(() => props.fiscal.totalTaxAll > 0);

const locationsHref = computed<string>(() => {
    const year = props.fiscal.year;

    return `/app/companies/${props.companyId}`
        + `?tab=contracts`
        + `&periodStart=${year}-01-01`
        + `&periodEnd=${year}-12-31`;
});

const vehiclesCount = computed<number>(() => props.fiscal.rows.length);

const co2Percent = computed<number>(() => {
    if (props.fiscal.totalTaxAll <= 0) {
        return 0;
    }

    return Math.round((props.fiscal.totalTaxCo2 / props.fiscal.totalTaxAll) * 100);
});

const pollutantsPercent = computed<number>(() => {
    if (props.fiscal.totalTaxAll <= 0) {
        return 0;
    }

    return 100 - co2Percent.value;
});
</script>

<template>
    <Card>
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-1.5">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Taxes {{ fiscal.year }}
                </p>
                <p class="font-mono text-4xl font-semibold tabular-nums text-slate-900">
                    {{ formatEur(fiscal.totalTaxAll) }}
                </p>
            </div>

            <div v-if="hasTax" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Taxe CO₂
                    </p>
                    <p class="font-mono text-xl font-semibold tabular-nums text-slate-900">
                        {{ formatEur(fiscal.totalTaxCo2) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-rose-400 transition-[width] duration-300"
                                :style="{ width: `${co2Percent}%` }"
                            />
                        </div>
                        <span class="text-[11px] font-medium tabular-nums text-slate-500">
                            {{ co2Percent }} %
                        </span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Taxe polluants
                    </p>
                    <p class="font-mono text-xl font-semibold tabular-nums text-slate-900">
                        {{ formatEur(fiscal.totalTaxPollutants) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-amber-400 transition-[width] duration-300"
                                :style="{ width: `${pollutantsPercent}%` }"
                            />
                        </div>
                        <span class="text-[11px] font-medium tabular-nums text-slate-500">
                            {{ pollutantsPercent }} %
                        </span>
                    </div>
                </div>
            </div>

            <dl v-if="hasActivity" class="grid grid-cols-3 gap-3">
                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Jours cumulés
                    </dt>
                    <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-900">
                        {{ fiscal.totalDays }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Véhicules taxés
                    </dt>
                    <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-900">
                        {{ vehiclesCount }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Locations
                    </dt>
                    <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-900">
                        {{ fiscal.contractsCount }}
                    </dd>
                </div>
            </dl>

            <p v-else class="text-sm italic text-slate-500">
                Aucune activité fiscale sur l'exercice {{ fiscal.year }}.
            </p>

            <Link
                v-if="hasActivity"
                :href="locationsHref"
                class="inline-flex w-fit cursor-pointer items-center gap-1 text-sm font-medium text-slate-600 underline-offset-2 transition-colors duration-[120ms] hover:text-slate-900 hover:underline"
            >
                Voir les locations {{ fiscal.year }}
                <ArrowRight :size="14" :stroke-width="1.75" />
            </Link>
        </div>
    </Card>
</template>

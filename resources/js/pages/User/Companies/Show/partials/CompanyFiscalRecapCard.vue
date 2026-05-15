<script setup lang="ts">
/**
 * Carte recap fiscale d'une entreprise pour l'exercice sélectionné.
 *
 * Refonte design D5.10.W · hiérarchie éditoriale ·
 *   1. Hero · eyebrow `MONTANT TOTAL` + grand nombre mono (text-5xl) +
 *      sous-ligne « CO2 X € · Polluants Y € » en mono discret.
 *   2. Stacked bar partagée · une seule barre horizontale segmentée en
 *      2 (CO2 slate-800 · Polluants slate-400) plutôt que 2 barres
 *      parallèles dupliquées, avec légende compacte au-dessus.
 *   3. KPIs activité · 3 stats (jours / véhicules / locations) en row
 *      bordée plutôt que bg-slate-50.
 *   4. Lien Locations footer · `inline-flex` discret, jamais en CTA.
 *
 * Empty state · si aucune activité (totalDays = 0 ET contractsCount = 0),
 * affiche un petit cartouche centré au lieu de la grille de KPIs vides.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as companyShow } from '@/routes/user/companies';
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

    return companyShow(props.companyId, {
        query: {
            tab: 'contracts',
            periodStart: `${year}-01-01`,
            periodEnd: `${year}-12-31`,
        },
    }).url;
});

const vehiclesCount = computed<number>(() => props.fiscal.rows.length);

const co2Percent = computed<number>(() => {
    if (props.fiscal.totalTaxAll <= 0) {
        return 0;
    }

    return Math.round((props.fiscal.totalTaxCo2 / props.fiscal.totalTaxAll) * 100);
});

const pollutantsPercent = computed<number>(() => 100 - co2Percent.value);
</script>

<template>
    <Card padding="lg">
        <div class="flex flex-col gap-8">
            <!-- Hero · montant total -->
            <div class="flex flex-col gap-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                    Montant total · {{ fiscal.year }}
                </p>
                <p class="font-mono text-5xl font-semibold tabular-nums leading-none text-slate-900">
                    {{ formatEur(fiscal.totalTaxAll) }}
                </p>
                <p
                    v-if="hasTax"
                    class="font-mono text-xs tabular-nums text-slate-500"
                >
                    CO₂ {{ formatEur(fiscal.totalTaxCo2) }}
                    <span class="mx-1.5 text-slate-300">·</span>
                    Polluants {{ formatEur(fiscal.totalTaxPollutants) }}
                </p>
            </div>

            <!-- Stacked bar partagée CO2 + Polluants -->
            <div v-if="hasTax" class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3 text-[11px] font-medium uppercase tracking-wide">
                    <span class="flex items-center gap-1.5 text-slate-700">
                        <span class="inline-block h-2 w-2 rounded-sm bg-slate-800" aria-hidden="true" />
                        Taxe CO₂
                        <span class="font-mono text-slate-500 normal-case tracking-normal">{{ co2Percent }} %</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-700">
                        Taxe polluants
                        <span class="font-mono text-slate-500 normal-case tracking-normal">{{ pollutantsPercent }} %</span>
                        <span class="inline-block h-2 w-2 rounded-sm bg-slate-300" aria-hidden="true" />
                    </span>
                </div>
                <div
                    class="flex h-2 overflow-hidden rounded-full bg-slate-100"
                    role="img"
                    :aria-label="`Répartition · ${co2Percent} % CO₂, ${pollutantsPercent} % polluants`"
                >
                    <div
                        class="bg-slate-800 transition-[width] duration-300"
                        :style="{ width: `${co2Percent}%` }"
                    />
                    <div
                        class="bg-slate-300 transition-[width] duration-300"
                        :style="{ width: `${pollutantsPercent}%` }"
                    />
                </div>
            </div>

            <!-- KPIs activité -->
            <dl v-if="hasActivity" class="grid grid-cols-3 gap-3 border-t border-slate-100 pt-6">
                <div class="flex flex-col gap-1">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Jours cumulés
                    </dt>
                    <dd class="font-mono text-2xl font-semibold tabular-nums leading-none text-slate-900">
                        {{ fiscal.totalDays }}
                    </dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Véhicules taxés
                    </dt>
                    <dd class="font-mono text-2xl font-semibold tabular-nums leading-none text-slate-900">
                        {{ vehiclesCount }}
                    </dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Locations
                    </dt>
                    <dd class="font-mono text-2xl font-semibold tabular-nums leading-none text-slate-900">
                        {{ fiscal.contractsCount }}
                    </dd>
                </div>
            </dl>

            <div
                v-else
                class="rounded-lg border border-dashed border-slate-200 bg-slate-50/40 px-4 py-3 text-center text-sm text-slate-500"
            >
                Aucune activité fiscale sur l'exercice {{ fiscal.year }}.
            </div>

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

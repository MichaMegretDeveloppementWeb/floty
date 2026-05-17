<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { computed, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';
import DashboardEvolutionChartSkeleton from './DashboardEvolutionChartSkeleton.vue';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps<{
    /** Onglet par défaut · auto-loadé en `Inertia::defer` avec la vague KPIs. */
    historyJoursVehicule?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    /** 3 autres onglets · `Inertia::optional` · hydratés au clic via `router.reload`. */
    historyContracts?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    historyTaxes?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    historyRecettes?: App.Data.User.Dashboard.DashboardHistoryPointData[];
}>();

type Dimension = 'joursVehicule' | 'contracts' | 'taxes' | 'recettes';

type DimensionMeta = {
    key: Dimension;
    /** Nom de la prop Inertia correspondante (sert au `router.reload({only: [...]})`). */
    propKey: 'historyJoursVehicule' | 'historyContracts' | 'historyTaxes' | 'historyRecettes';
    label: string;
    color: string;
    format: (v: number) => string;
    /** Transformation appliquée avant injection dans Chart.js (ex. cents → €). */
    transform?: (v: number) => number;
};

const DIMENSIONS: readonly DimensionMeta[] = [
    {
        key: 'joursVehicule',
        propKey: 'historyJoursVehicule',
        label: 'Jours-véhicule',
        color: '#334155',
        format: (v) => v.toLocaleString('fr-FR'),
    },
    {
        key: 'contracts',
        propKey: 'historyContracts',
        label: 'Locations',
        color: '#0f766e',
        format: (v) => v.toLocaleString('fr-FR'),
    },
    {
        key: 'taxes',
        propKey: 'historyTaxes',
        label: 'Taxes dues',
        color: '#b45309',
        format: (v) => formatEur(v),
    },
    {
        key: 'recettes',
        propKey: 'historyRecettes',
        label: 'Recettes',
        color: '#4338ca',
        format: (v) => formatEur(v),
        transform: (cents) => cents / 100,
    },
];

const activeDimension = ref<Dimension>('joursVehicule');
const loadingDimension = ref<Dimension | null>(null);

const activeMeta = computed<DimensionMeta>(
    () => DIMENSIONS.find((d) => d.key === activeDimension.value)!,
);

/** Données de l'onglet actif · `undefined` tant que la prop n'est pas hydratée. */
const activeData = computed<App.Data.User.Dashboard.DashboardHistoryPointData[] | undefined>(
    () => props[activeMeta.value.propKey],
);

const isLoading = computed<boolean>(
    () => loadingDimension.value === activeDimension.value && activeData.value === undefined,
);

function setDimension(d: Dimension): void {
    activeDimension.value = d;
    const meta = DIMENSIONS.find((m) => m.key === d)!;
    // Déjà hydraté (Inertia conserve la prop après 1er reload) · rien à faire.
    if (props[meta.propKey] !== undefined) {
        return;
    }
    // Déjà en cours pour cet onglet · skip pour éviter double-fetch.
    if (loadingDimension.value === d) {
        return;
    }
    loadingDimension.value = d;
    router.reload({
        only: [meta.propKey],
        onFinish: () => {
            if (loadingDimension.value === d) {
                loadingDimension.value = null;
            }
        },
    });
}

const chartData = computed(() => {
    const points = activeData.value ?? [];

    return {
        labels: points.map((p) =>
            p.isCurrentYear ? `${p.year} (en cours)` : String(p.year),
        ),
        datasets: [
            {
                label: activeMeta.value.label,
                data: points.map((p) => {
                    return activeMeta.value.transform
                        ? activeMeta.value.transform(p.value)
                        : p.value;
                }),
                backgroundColor: points.map((p) =>
                    p.isCurrentYear
                        ? `${activeMeta.value.color}99`
                        : activeMeta.value.color,
                ),
                borderRadius: 4,
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context: { parsed: { y: number | null } }): string =>
                    activeMeta.value.format(context.parsed.y ?? 0),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value: number | string) =>
                    activeMeta.value.format(Number(value)),
            },
        },
    },
}));
</script>

<template>
    <Card>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Évolution annuelle
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Comparaison sur les dernières années
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5 rounded-lg border border-slate-200 bg-white p-1">
                    <button
                        v-for="dim in DIMENSIONS"
                        :key="dim.key"
                        type="button"
                        :class="[
                            'rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[120ms]',
                            activeDimension === dim.key
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-50',
                        ]"
                        @click="setDimension(dim.key)"
                    >
                        {{ dim.label }}
                    </button>
                </div>
            </div>
        </template>

        <div class="h-[280px]" :aria-busy="isLoading">
            <DashboardEvolutionChartSkeleton v-if="isLoading" chart-only />
            <Bar v-else :data="chartData" :options="chartOptions" />
        </div>
    </Card>
</template>

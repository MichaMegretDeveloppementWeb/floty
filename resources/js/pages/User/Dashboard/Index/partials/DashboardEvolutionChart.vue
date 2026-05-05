<script setup lang="ts">
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

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps<{
    history: App.Data.User.Dashboard.DashboardYearHistoryData[];
}>();

type Dimension = 'joursVehicule' | 'contractsActifs' | 'taxesDues' | 'tauxOccupation';

type DimensionMeta = {
    key: Dimension;
    label: string;
    /** Couleur principale Tailwind slate-700. */
    color: string;
    /** Formate la valeur Y pour l'axe et le tooltip. */
    format: (v: number) => string;
};

const DIMENSIONS: readonly DimensionMeta[] = [
    {
        key: 'joursVehicule',
        label: 'Jours-véhicule',
        color: '#334155', // slate-700
        format: (v) => v.toLocaleString('fr-FR'),
    },
    {
        key: 'contractsActifs',
        label: 'Contrats actifs',
        color: '#0f766e', // teal-700
        format: (v) => v.toLocaleString('fr-FR'),
    },
    {
        key: 'taxesDues',
        label: 'Taxes dues',
        color: '#b45309', // amber-700
        format: (v) => formatEur(v),
    },
    {
        key: 'tauxOccupation',
        label: "Taux d'occupation",
        color: '#4338ca', // indigo-700
        format: (v) => `${v.toLocaleString('fr-FR')} %`,
    },
];

const activeDimension = ref<Dimension>('joursVehicule');

const activeMeta = computed<DimensionMeta>(
    () => DIMENSIONS.find((d) => d.key === activeDimension.value)!,
);

const chartData = computed(() => ({
    labels: props.history.map((h) =>
        h.isCurrentYear ? `${h.year} (en cours)` : String(h.year),
    ),
    datasets: [
        {
            label: activeMeta.value.label,
            data: props.history.map((h) => h[activeDimension.value]),
            backgroundColor: props.history.map((h) =>
                h.isCurrentYear
                    ? `${activeMeta.value.color}99` // 60% opacity sur année en cours
                    : activeMeta.value.color,
            ),
            borderRadius: 4,
        },
    ],
}));

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

function setDimension(d: Dimension): void {
    activeDimension.value = d;
}
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
                        Comparaison sur les {{ history.length }} dernières années
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

        <div class="h-[280px]">
            <Bar :data="chartData" :options="chartOptions" />
        </div>
    </Card>
</template>

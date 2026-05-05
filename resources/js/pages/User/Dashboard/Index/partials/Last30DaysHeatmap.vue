<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as vehicleShowRoute } from '@/routes/user/vehicles';

const props = defineProps<{
    vehicles: App.Data.User.Dashboard.DashboardVehicleHeatmapData[];
}>();

type DayMeta = { date: string; label: string; isFirstOfMonth: boolean };

/**
 * Construit les libellés de l'en-tête de la heatmap depuis les jours
 * du premier véhicule (ils sont identiques pour tous les véhicules par
 * construction backend). Si la liste est vide, fallback sur 30 jours
 * vides à partir d'aujourd'hui.
 */
const dayHeaders = computed<DayMeta[]>(() => {
    const firstVehicle = props.vehicles[0];

    if (!firstVehicle) {
        return [];
    }

    return firstVehicle.days.map((day) => {
        const d = new Date(day.date);

        return {
            date: day.date,
            label: String(d.getDate()),
            isFirstOfMonth: d.getDate() === 1,
        };
    });
});

function cellClass(status: string): string {
    if (status === 'occupied') {
        return 'bg-slate-700';
    }

    if (status === 'unavailable') {
        return 'bg-rose-300';
    }

    return 'bg-slate-100';
}

function cellTitle(date: string, status: string): string {
    const formatted = new Date(date).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
    });
    const statusLabel
        = status === 'occupied'
            ? 'Occupé (contrat actif)'
            : status === 'unavailable'
                ? 'Indisponible'
                : 'Libre';

    return `${formatted} — ${statusLabel}`;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-if="vehicles.length === 0" class="text-sm text-slate-500">
            Aucun véhicule actif dans la fenêtre des 30 derniers jours.
        </div>
        <!--
            Sur mobile la grille déborderait facilement (30 colonnes de
            cellules + colonne plaque). On wrap dans un overflow-x-auto
            avec une min-width sur le contenu pour préserver lisibilité
            puis scroll horizontal naturel. Plaque sticky-left garde le
            repère visible pendant le scroll.
        -->
        <div v-else class="-mx-2 overflow-x-auto px-2">
            <div class="flex min-w-[640px] flex-col gap-1.5">
                <!-- En-tête : jours du mois -->
                <div class="flex items-center gap-1 pl-[104px]">
                    <div
                        v-for="day in dayHeaders"
                        :key="day.date"
                        :class="[
                            'min-w-[16px] flex-1 text-center font-mono text-[10px]',
                            day.isFirstOfMonth ? 'font-semibold text-slate-700' : 'text-slate-400',
                        ]"
                    >
                        {{ day.label }}
                    </div>
                </div>

                <!-- Lignes véhicules -->
                <div
                    v-for="vehicle in vehicles"
                    :key="vehicle.vehicleId"
                    class="flex items-center gap-1"
                >
                    <Link
                        :href="vehicleShowRoute({ vehicle: vehicle.vehicleId }).url"
                        class="sticky left-0 z-10 w-[100px] shrink-0 bg-white pr-1 text-xs text-slate-700 hover:text-slate-900 hover:underline"
                        :title="`${vehicle.brand} ${vehicle.model}`"
                    >
                        <Plate :value="vehicle.licensePlate" />
                    </Link>
                    <div class="flex flex-1 items-center gap-1">
                        <div
                            v-for="day in vehicle.days"
                            :key="day.date"
                            :class="[
                                'h-4 min-w-[16px] flex-1 rounded-sm transition-opacity duration-[120ms] hover:opacity-70',
                                cellClass(day.status),
                            ]"
                            :title="cellTitle(day.date, day.status)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Légende -->
        <div v-if="vehicles.length > 0" class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm bg-slate-700" />
                Occupé
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm bg-rose-300" />
                Indisponible
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-3 w-3 rounded-sm bg-slate-100" />
                Libre
            </span>
        </div>
    </div>
</template>

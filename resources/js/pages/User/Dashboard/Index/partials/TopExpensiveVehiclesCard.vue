<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as vehicleShowRoute } from '@/routes/user/vehicles';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehicles: App.Data.User.Dashboard.DashboardTopVehicleData[];
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">
            Top véhicules (taxes YTD)
        </h3>
        <div v-if="vehicles.length === 0" class="text-sm text-slate-500">
            Aucun véhicule taxé cette année.
        </div>
        <ul v-else class="flex flex-col divide-y divide-slate-100">
            <li
                v-for="vehicle in vehicles"
                :key="vehicle.vehicleId"
                class="py-2"
            >
                <Link
                    :href="vehicleShowRoute({ vehicle: vehicle.vehicleId }).url"
                    class="group flex items-start justify-between gap-3 hover:text-slate-900"
                >
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <Plate :value="vehicle.licensePlate" />
                        <p class="truncate text-xs text-slate-500">
                            {{ vehicle.brand }} {{ vehicle.model }}
                        </p>
                    </div>
                    <p class="shrink-0 font-mono text-sm font-semibold text-slate-900 tabular-nums">
                        {{ formatEur(vehicle.taxYearToDate) }}
                    </p>
                </Link>
            </li>
        </ul>
    </div>
</template>

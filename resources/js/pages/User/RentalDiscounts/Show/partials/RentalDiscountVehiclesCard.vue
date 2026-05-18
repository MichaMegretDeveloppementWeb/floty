<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Car } from 'lucide-vue-next';
import { show as vehicleShowRoute } from '@/routes/user/vehicles';

defineProps<{
    vehicles: readonly App.Data.User.RentalDiscount.RentalDiscountVehicleTagData[];
    isAllVehicles: boolean;
}>();
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white">
        <header class="flex items-baseline justify-between gap-3 px-5 py-3.5 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-900">
                Périmètre véhicules
            </h2>
            <p v-if="!isAllVehicles" class="font-mono text-xs text-slate-500 tabular-nums">
                {{ vehicles.length }} véhicule{{ vehicles.length > 1 ? 's' : '' }}
            </p>
        </header>

        <div v-if="isAllVehicles" class="flex items-center gap-4 px-5 py-4">
            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500">
                <Car :size="16" :stroke-width="1.75" />
            </span>
            <div class="flex-1">
                <p class="text-sm font-medium text-slate-900">
                    Tous les véhicules de l'entreprise
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Inclut les véhicules ajoutés ultérieurement, sans liste explicite.
                </p>
            </div>
            <span class="font-mono text-xs text-slate-500 tabular-nums">
                périmètre dynamique
            </span>
        </div>

        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                class="group flex items-center gap-4 px-5 py-3 transition-colors hover:bg-slate-50"
            >
                <span class="font-mono text-sm font-medium text-slate-900 tabular-nums">
                    {{ vehicle.licensePlate }}
                </span>
                <span class="flex-1 truncate text-sm text-slate-600">
                    {{ vehicle.brand }} {{ vehicle.model }}
                </span>
                <Link
                    :href="vehicleShowRoute.url({ vehicle: vehicle.id })"
                    class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition-colors group-hover:text-slate-900"
                >
                    Voir
                    <ArrowUpRight :size="12" :stroke-width="1.75" />
                </Link>
            </li>
        </ul>
    </section>
</template>

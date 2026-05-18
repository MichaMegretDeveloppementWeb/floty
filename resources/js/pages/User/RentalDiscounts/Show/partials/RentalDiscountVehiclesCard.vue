<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Car } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as vehicleShowRoute } from '@/routes/user/vehicles';

defineProps<{
    vehicles: readonly App.Data.User.RentalDiscount.RentalDiscountVehicleTagData[];
    isAllVehicles: boolean;
}>();
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">
                    Véhicules concernés
                </h2>
                <p v-if="!isAllVehicles" class="text-xs text-slate-500">
                    {{ vehicles.length }} véhicule{{ vehicles.length > 1 ? 's' : '' }}
                </p>
            </div>
        </template>

        <div v-if="isAllVehicles" class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/40 px-4 py-3">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                <Car :size="18" :stroke-width="1.75" />
            </span>
            <div class="flex flex-col gap-0.5">
                <p class="text-sm font-medium text-blue-900">
                    Tous les véhicules de l'entreprise
                </p>
                <p class="text-xs text-blue-700">
                    La réduction s'applique à l'ensemble des véhicules utilisés par l'entreprise sur la période, sans liste explicite.
                </p>
            </div>
        </div>

        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                class="flex items-center justify-between gap-3 py-2"
            >
                <div class="flex items-center gap-3">
                    <span class="font-mono text-sm font-medium text-slate-900">
                        {{ vehicle.licensePlate }}
                    </span>
                    <span class="text-sm text-slate-600">
                        {{ vehicle.brand }} {{ vehicle.model }}
                    </span>
                </div>
                <Link
                    :href="vehicleShowRoute.url({ vehicle: vehicle.id })"
                    class="text-xs font-medium text-blue-600 hover:underline"
                >
                    Voir
                </Link>
            </li>
        </ul>
    </Card>
</template>

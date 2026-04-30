<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, Pencil } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { useVehicleHeader } from '@/Composables/Vehicle/Show/useVehicleHeader';
import { edit as vehiclesEditRoute, index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import { vehicleStatusLabel } from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();

const { statusTone, secondaryInfo } = useVehicleHeader(props);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Link
            :href="vehiclesIndexRoute.url()"
            class="inline-flex w-fit items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            Retour à la flotte
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <Plate :value="props.vehicle.licensePlate" />
                    <StatusPill :tone="statusTone[props.vehicle.currentStatus]">
                        {{ vehicleStatusLabel[props.vehicle.currentStatus] }}
                    </StatusPill>
                </div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    {{ props.vehicle.brand }} {{ props.vehicle.model }}
                </h1>
                <div class="flex items-center flex-wrap text-sm text-slate-500 mt-3">
                    <div
                        v-for="(part, idx) in secondaryInfo"
                        :key="part"
                    >
                        <span>{{ part }}</span>
                        <span v-if="idx < (secondaryInfo.length-1)" class="mx-2 text-slate-300">·</span>
                    </div>
                </div>
                <p
                    v-if="props.vehicle.notes"
                    class="mt-1 max-w-3xl rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm whitespace-pre-line text-slate-700"
                >
                    {{ props.vehicle.notes }}
                </p>
            </div>

            <Link :href="vehiclesEditRoute.url({ vehicle: props.vehicle.id })">
                <Button variant="secondary" size="sm">
                    <template #icon-left>
                        <Pencil :size="14" :stroke-width="1.75" />
                    </template>
                    Modifier
                </Button>
            </Link>
        </header>
    </div>
</template>

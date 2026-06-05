<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Ban, ChevronLeft, Pencil, Trash2, TrendingDown } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import FlagIcon from '@/Components/Ui/FlagIcon.vue';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { edit as editRoute } from '@/routes/user/vehicles/events';
import {
    vehicleEventDisplayCategory,
    vehicleEventDisplayTitle,
    vehicleEventTypeLabel,
} from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleHeader = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
};
type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicle: VehicleHeader;
    vehicleEvent: VehicleEvent;
}>();

defineEmits<{
    'open-delete': [];
}>();

const backUrl = vehiclesShowRoute.url({ vehicle: props.vehicle.id }, { query: { tab: 'events' } });
</script>

<template>
    <div class="flex flex-col gap-4">
        <Link
            :href="backUrl"
            class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            {{ vehicle.licensePlate }} · Événements
        </Link>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-[34px] font-semibold leading-tight tracking-tight text-slate-900">
                    {{ vehicleEventDisplayTitle(vehicleEvent) }}
                </h1>
                <p
                    v-if="vehicleEvent.type !== 'other'"
                    class="text-sm text-slate-500"
                >
                    {{ vehicleEventTypeLabel[vehicleEvent.type] }}
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <Badge tone="slate" :uppercase="false">
                        {{ vehicleEventDisplayCategory(vehicleEvent) }}
                    </Badge>
                    <FlagIcon
                        v-if="vehicleEvent.impliesUnavailability"
                        :icon="Ban"
                        tone="amber"
                        label="Génère une indisponibilité"
                    />
                    <FlagIcon
                        v-if="vehicleEvent.hasFiscalImpact"
                        :icon="TrendingDown"
                        tone="rose"
                        label="Fiscalement réducteur"
                    />
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <Link
                    :href="editRoute.url({ vehicle: vehicle.id, vehicleEvent: vehicleEvent.id })"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <Pencil :size="12" :stroke-width="1.75" />
                    Modifier
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                    @click="$emit('open-delete')"
                >
                    <Trash2 :size="12" :stroke-width="1.75" />
                    Supprimer
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import type { StatusTone } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();

const statusLabel: Record<App.Enums.Vehicle.VehicleStatus, string> = {
    active: 'Actif',
    maintenance: 'Maintenance',
    sold: 'Vendu',
    destroyed: 'Détruit',
    other: 'Autre',
};

const statusTone: Record<App.Enums.Vehicle.VehicleStatus, StatusTone> = {
    active: 'emerald',
    maintenance: 'amber',
    sold: 'slate',
    destroyed: 'rose',
    other: 'slate',
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <Link
            :href="vehiclesIndexRoute.url()"
            class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            Retour à la flotte
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <p class="eyebrow">Fiche véhicule</p>
                <div class="flex items-center gap-3">
                    <Plate :value="props.vehicle.licensePlate" />
                    <StatusPill :tone="statusTone[props.vehicle.currentStatus]">
                        {{ statusLabel[props.vehicle.currentStatus] }}
                    </StatusPill>
                </div>
                <h1
                    class="text-xl font-semibold tracking-tight text-slate-900 md:text-2xl"
                >
                    {{ props.vehicle.brand }} {{ props.vehicle.model }}
                </h1>
                <dl
                    class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm text-slate-600 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="flex flex-col">
                        <dt class="text-xs text-slate-400 uppercase">
                            Date d'acquisition
                        </dt>
                        <dd class="font-medium text-slate-700">
                            {{ formatDateFr(props.vehicle.acquisitionDate) }}
                        </dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-xs text-slate-400 uppercase">
                            1ʳᵉ immatriculation française
                        </dt>
                        <dd class="font-medium text-slate-700">
                            {{
                                formatDateFr(
                                    props.vehicle.firstFrenchRegistrationDate,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-xs text-slate-400 uppercase">
                            1ʳᵉ mise en service économique
                        </dt>
                        <dd class="font-medium text-slate-700">
                            {{
                                formatDateFr(props.vehicle.firstEconomicUseDate)
                            }}
                        </dd>
                    </div>
                    <div v-if="props.vehicle.vin" class="flex flex-col">
                        <dt class="text-xs text-slate-400 uppercase">VIN</dt>
                        <dd class="font-mono text-sm text-slate-700">
                            {{ props.vehicle.vin }}
                        </dd>
                    </div>
                    <div v-if="props.vehicle.color" class="flex flex-col">
                        <dt class="text-xs text-slate-400 uppercase">
                            Couleur
                        </dt>
                        <dd class="font-medium text-slate-700">
                            {{ props.vehicle.color }}
                        </dd>
                    </div>
                    <div
                        v-if="props.vehicle.mileageCurrent !== null"
                        class="flex flex-col"
                    >
                        <dt class="text-xs text-slate-400 uppercase">
                            Kilométrage
                        </dt>
                        <dd class="font-medium text-slate-700">
                            {{ props.vehicle.mileageCurrent.toLocaleString('fr-FR') }} km
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="props.vehicle.notes"
                    class="max-w-3xl rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm whitespace-pre-line text-slate-700"
                >
                    {{ props.vehicle.notes }}
                </p>
            </div>
        </header>
    </div>
</template>

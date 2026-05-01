<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArchiveRestore, ChevronLeft, LogOut, Pencil } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { useVehicleHeader } from '@/Composables/Vehicle/Show/useVehicleHeader';
import { useVehicleShowActions } from '@/Composables/Vehicle/Show/useVehicleShowActions';
import { edit as vehiclesEditRoute, index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { vehicleExitReasonLabel, vehicleStatusLabel } from '@/Utils/labels/vehicleEnumLabels';
import ExitVehicleModal from './ExitVehicleModal.vue';
import ReactivateVehicleModal from './ReactivateVehicleModal.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();

const { statusTone, secondaryInfo } = useVehicleHeader(props);
const {
    exitModalOpen,
    reactivateModalOpen,
    openExit,
    openReactivate,
} = useVehicleShowActions();
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

        <div
            v-if="props.vehicle.isExited && props.vehicle.exitDate && props.vehicle.exitReason"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-300 bg-slate-100 px-4 py-2.5 text-sm text-slate-700"
            role="status"
        >
            <span class="rounded-md bg-slate-200 px-2 py-0.5 text-xs font-semibold tracking-wide text-slate-700 uppercase">
                Retiré
            </span>
            <span>
                {{ vehicleExitReasonLabel[props.vehicle.exitReason] }} le
                <span class="font-medium text-slate-900">
                    {{ formatDateFr(props.vehicle.exitDate) }}
                </span>
            </span>
        </div>

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
                <div class="mt-3 flex flex-wrap items-center text-sm text-slate-500">
                    <div
                        v-for="(part, idx) in secondaryInfo"
                        :key="part"
                    >
                        <span>{{ part }}</span>
                        <span v-if="idx < (secondaryInfo.length - 1)" class="mx-2 text-slate-300">·</span>
                    </div>
                </div>
                <p
                    v-if="props.vehicle.notes"
                    class="mt-1 max-w-3xl rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm whitespace-pre-line text-slate-700"
                >
                    {{ props.vehicle.notes }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link :href="vehiclesEditRoute.url({ vehicle: props.vehicle.id })">
                    <Button variant="secondary" size="sm">
                        <template #icon-left>
                            <Pencil :size="14" :stroke-width="1.75" />
                        </template>
                        Modifier
                    </Button>
                </Link>
                <Button
                    v-if="!props.vehicle.isExited"
                    variant="secondary"
                    size="sm"
                    @click="openExit"
                >
                    <template #icon-left>
                        <LogOut :size="14" :stroke-width="1.75" />
                    </template>
                    Retirer
                </Button>
                <Button
                    v-else
                    variant="secondary"
                    size="sm"
                    @click="openReactivate"
                >
                    <template #icon-left>
                        <ArchiveRestore :size="14" :stroke-width="1.75" />
                    </template>
                    Réactiver
                </Button>
            </div>
        </header>

        <ExitVehicleModal
            v-if="!props.vehicle.isExited"
            v-model:open="exitModalOpen"
            :vehicle-id="props.vehicle.id"
            :license-plate="props.vehicle.licensePlate"
        />

        <ReactivateVehicleModal
            v-if="props.vehicle.isExited && props.vehicle.exitDate && props.vehicle.exitReason"
            v-model:open="reactivateModalOpen"
            :vehicle-id="props.vehicle.id"
            :license-plate="props.vehicle.licensePlate"
            :exit-date="props.vehicle.exitDate"
            :exit-reason="props.vehicle.exitReason"
        />
    </div>
</template>

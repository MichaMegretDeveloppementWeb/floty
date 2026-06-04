<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { useVehicleEventShow } from '@/Composables/VehicleEvent/Show/useVehicleEventShow';
import { vehicleEventDisplayTitle } from '@/Utils/labels/vehicleEventEnumLabels';
import VehicleEventShowBody from './partials/VehicleEventShowBody.vue';
import VehicleEventShowHeader from './partials/VehicleEventShowHeader.vue';

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

const { deleteModalOpen, periodLabel, openDelete, confirmDelete } = useVehicleEventShow(
    () => props.vehicleEvent,
);

const pageTitle = computed<string>(() => vehicleEventDisplayTitle(props.vehicleEvent));
</script>

<template>
    <Head :title="pageTitle" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-3xl flex-col gap-6">
            <VehicleEventShowHeader
                :vehicle="props.vehicle"
                :vehicle-event="props.vehicleEvent"
                @open-delete="openDelete"
            />

            <VehicleEventShowBody
                :vehicle-event="props.vehicleEvent"
                :period-label="periodLabel"
            />

            <ConfirmModal
                v-model:open="deleteModalOpen"
                title="Supprimer cet événement"
                message="Cette action est irréversible."
                confirm-label="Supprimer"
                tone="danger"
                @confirm="confirmDelete"
            />
        </div>
    </UserLayout>
</template>

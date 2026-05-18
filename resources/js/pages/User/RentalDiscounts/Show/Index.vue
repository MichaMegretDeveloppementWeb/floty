<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useRentalDiscountShow } from '@/Composables/RentalDiscount/Show/useRentalDiscountShow';
import RentalDiscountDeleteModal from './partials/RentalDiscountDeleteModal.vue';
import RentalDiscountHeader from './partials/RentalDiscountHeader.vue';
import RentalDiscountKeyTiles from './partials/RentalDiscountKeyTiles.vue';
import RentalDiscountKpiStrip from './partials/RentalDiscountKpiStrip.vue';
import RentalDiscountNotesStrip from './partials/RentalDiscountNotesStrip.vue';
import RentalDiscountVehiclesCard from './partials/RentalDiscountVehiclesCard.vue';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const { deleteModalOpen, openDelete, closeDelete, confirmDelete } = useRentalDiscountShow();
</script>

<template>
    <Head :title="props.rentalDiscount.label ?? `Réduction #${props.rentalDiscount.id}`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-5xl flex-col gap-6">
            <RentalDiscountHeader
                :rental-discount="props.rentalDiscount"
                @open-delete="openDelete"
            />

            <RentalDiscountKeyTiles :rental-discount="props.rentalDiscount" />

            <RentalDiscountKpiStrip :rental-discount="props.rentalDiscount" />

            <RentalDiscountVehiclesCard
                :vehicles="props.rentalDiscount.vehicles"
                :is-all-vehicles="props.rentalDiscount.isAllVehicles"
            />

            <RentalDiscountNotesStrip
                v-if="props.rentalDiscount.notes"
                :notes="props.rentalDiscount.notes"
            />

            <RentalDiscountDeleteModal
                v-if="deleteModalOpen"
                :rental-discount="props.rentalDiscount"
                @close="closeDelete"
                @confirm="confirmDelete"
            />
        </div>
    </UserLayout>
</template>

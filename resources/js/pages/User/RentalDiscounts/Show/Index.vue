<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useRentalDiscountShow } from '@/Composables/RentalDiscount/Show/useRentalDiscountShow';
import RentalDiscountActionsBar from './partials/RentalDiscountActionsBar.vue';
import RentalDiscountApplicationCard from './partials/RentalDiscountApplicationCard.vue';
import RentalDiscountDeleteModal from './partials/RentalDiscountDeleteModal.vue';
import RentalDiscountHeader from './partials/RentalDiscountHeader.vue';
import RentalDiscountVehiclesCard from './partials/RentalDiscountVehiclesCard.vue';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const { deleteModalOpen, openDelete, closeDelete, confirmDelete } = useRentalDiscountShow();
</script>

<template>
    <Head :title="props.rentalDiscount.label ?? `Réduction #${props.rentalDiscount.id}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <RentalDiscountHeader :rental-discount="props.rentalDiscount" />

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="flex flex-col gap-6 xl:col-span-2">
                    <RentalDiscountActionsBar
                        class="xl:hidden"
                        :rental-discount-id="props.rentalDiscount.id"
                        @open-delete="openDelete"
                    />
                    <RentalDiscountApplicationCard :rental-discount="props.rentalDiscount" />
                    <RentalDiscountVehiclesCard
                        :vehicles="props.rentalDiscount.vehicles"
                        :is-all-vehicles="props.rentalDiscount.isAllVehicles"
                    />
                </div>

                <aside class="hidden xl:col-span-1 xl:block">
                    <RentalDiscountActionsBar
                        :rental-discount-id="props.rentalDiscount.id"
                        @open-delete="openDelete"
                    />
                </aside>
            </div>

            <RentalDiscountDeleteModal
                v-if="deleteModalOpen"
                :rental-discount="props.rentalDiscount"
                @close="closeDelete"
                @confirm="confirmDelete"
            />
        </div>
    </UserLayout>
</template>

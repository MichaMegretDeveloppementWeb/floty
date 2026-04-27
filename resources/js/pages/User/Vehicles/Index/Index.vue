<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import EmptyFleetState from './partials/EmptyFleetState.vue';
import FleetTable from './partials/FleetTable.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.VehicleListItemData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <EmptyFleetState v-if="props.vehicles.length === 0" />
            <FleetTable
                v-else
                :vehicles="props.vehicles"
                :fiscal-year="fiscalYear"
            />
        </div>
    </UserLayout>
</template>

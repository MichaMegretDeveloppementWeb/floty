<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ContractsTable from './partials/ContractsTable.vue';
import EmptyContractsState from './partials/EmptyContractsState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    contracts: App.Data.User.Contract.ContractListItemData[];
}>();
</script>

<template>
    <Head title="Contrats" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader />

            <EmptyContractsState v-if="props.contracts.length === 0" />
            <ContractsTable v-else :contracts="props.contracts" />
        </div>
    </UserLayout>
</template>

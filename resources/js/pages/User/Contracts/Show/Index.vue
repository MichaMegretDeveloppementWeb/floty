<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ActionsBar from './partials/ActionsBar.vue';
import ContractDetails from './partials/ContractDetails.vue';
import ContractHeader from './partials/ContractHeader.vue';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
</script>

<template>
    <Head :title="`Contrat ${props.contract.vehicleLicensePlate} · ${props.contract.companyShortCode}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <ContractHeader :contract="props.contract" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <ContractDetails :contract="props.contract" />
                </div>
                <div>
                    <ActionsBar :contract-id="props.contract.id" />
                </div>
            </div>
        </div>
    </UserLayout>
</template>

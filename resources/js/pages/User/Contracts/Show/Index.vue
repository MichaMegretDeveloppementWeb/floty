<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ActionsBar from './partials/ActionsBar.vue';
import ContractDetails from './partials/ContractDetails.vue';
import ContractEntityCards from './partials/ContractEntityCards.vue';
import ContractTitle from './partials/ContractTitle.vue';
import TaxBreakdownPanel from './partials/TaxBreakdownPanel.vue';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
    taxBreakdown: App.Data.User.Contract.ContractTaxBreakdownData | null;
}>();
</script>

<template>
    <Head :title="`Contrat ${props.contract.vehicleLicensePlate} · ${props.contract.companyShortCode}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <ContractTitle :contract="props.contract" />
            <ContractEntityCards :contract="props.contract" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <ContractDetails :contract="props.contract" />
                    <TaxBreakdownPanel :tax-breakdown="props.taxBreakdown" />
                </div>
                <div>
                    <ActionsBar :contract-id="props.contract.id" />
                </div>
            </div>
        </div>
    </UserLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useCompanyTabs } from '@/Composables/Company/Show/useCompanyTabs';
import CompanyBillingTab from './partials/CompanyBillingTab.vue';
import CompanyContractsTab from './partials/CompanyContractsTab.vue';
import CompanyDriversTab from './partials/CompanyDriversTab.vue';
import CompanyFiscalTab from './partials/CompanyFiscalTab.vue';
import CompanyHeader from './partials/CompanyHeader.vue';
import CompanyInfoTab from './partials/CompanyInfoTab.vue';
import CompanyTabsNav from './partials/CompanyTabsNav.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
}>();

const { activeTab, setTab } = useCompanyTabs();
</script>

<template>
    <Head :title="props.company.legalName" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <CompanyHeader :company="props.company" />

            <CompanyTabsNav :active-tab="activeTab" @change="setTab" />

            <CompanyInfoTab v-if="activeTab === 'infos'" :company="props.company" />
            <CompanyContractsTab v-else-if="activeTab === 'contracts'" :company="props.company" />
            <CompanyDriversTab v-else-if="activeTab === 'drivers'" :drivers="props.company.drivers" />
            <CompanyFiscalTab v-else-if="activeTab === 'fiscal'" />
            <CompanyBillingTab v-else-if="activeTab === 'billing'" />
        </div>
    </UserLayout>
</template>

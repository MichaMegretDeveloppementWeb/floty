<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useCompanyTabs } from '@/Composables/Company/Show/useCompanyTabs';
import CompanyBillingTab from './partials/CompanyBillingTab.vue';
import CompanyContractsTab from './partials/CompanyContractsTab.vue';
import CompanyDriversTab from './partials/CompanyDriversTab.vue';
import CompanyFiscalTab from './partials/CompanyFiscalTab.vue';
import CompanyHeader from './partials/CompanyHeader.vue';
import CompanyOverviewTab from './partials/CompanyOverviewTab.vue';
import CompanyTabsNav from './partials/CompanyTabsNav.vue';

type DriverOption = { id: number; fullName: string; initials: string };

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    options: {
        drivers: DriverOption[];
    };
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsStats: App.Data.User.Company.CompanyContractsStatsData;
    contractsAvailableYears: number[];
    companyFiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    billingYear: number;
    // Phase 11 D4 + D5.8 · Déclarations fiscales
    pendingDeclarations: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    declarationLifecycle: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
}>();

const { activeTab, setTab } = useCompanyTabs();

/**
 * Phase 11 D4 — Navigation depuis l'alerte « Déclarations à finaliser »
 * vers l'onglet Fiscalité de l'année concernée. URL `?tab=fiscal&fiscalYear=Y`
 * pilote `useCompanyTabs` (lit `?tab=`) + `useCompanyFiscalSelectedYear`
 * (lit `?fiscalYear=`).
 */
function handleGotoFiscalYear(year: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'fiscal');
    url.searchParams.set('fiscalYear', String(year));
    router.visit(url.pathname + '?' + url.searchParams.toString(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="props.company.legalName" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <CompanyHeader :company="props.company" />

            <CompanyTabsNav :active-tab="activeTab" @change="setTab" />

            <CompanyOverviewTab
                v-if="activeTab === 'overview'"
                :company="props.company"
                :pending-declarations="props.pendingDeclarations"
                @goto-fiscal-year="handleGotoFiscalYear"
            />
            <CompanyContractsTab
                v-else-if="activeTab === 'contracts'"
                :company="props.company"
                :contracts="props.contracts"
                :contracts-query="props.contractsQuery"
                :contracts-stats="props.contractsStats"
                :contracts-available-years="props.contractsAvailableYears"
            />
            <CompanyDriversTab
                v-else-if="activeTab === 'drivers'"
                :company-id="props.company.id"
                :company-legal-name="props.company.legalName"
                :drivers="props.company.drivers"
                :available-drivers="props.options.drivers"
            />
            <CompanyFiscalTab
                v-else-if="activeTab === 'fiscal'"
                :fiscal="props.companyFiscal"
                :company-id="props.company.id"
                :declaration-lifecycle="props.declarationLifecycle"
            />
            <CompanyBillingTab
                v-else-if="activeTab === 'billing'"
                :company-id="props.company.id"
                :monthly-billing="props.companyBilling"
                :available-years="props.contractsAvailableYears"
                :active-year="props.billingYear"
            />
        </div>
    </UserLayout>
</template>

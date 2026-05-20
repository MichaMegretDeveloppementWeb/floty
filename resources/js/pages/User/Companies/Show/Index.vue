<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useCompanyTabs } from '@/Composables/Company/Show/useCompanyTabs';
import CompanyBillingTab from './partials/CompanyBillingTab.vue';
import CompanyContractsTab from './partials/CompanyContractsTab.vue';
import CompanyDriversTab from './partials/CompanyDriversTab.vue';
import CompanyFiscalTab from './partials/CompanyFiscalTab.vue';
import CompanyHeader from './partials/CompanyHeader.vue';
import CompanyOverviewTab from './partials/CompanyOverviewTab.vue';
import CompanyTabsNav from './partials/CompanyTabsNav.vue';
import TabLoadingSkeleton from './partials/TabLoadingSkeleton.vue';

type DriverOption = { id: number; fullName: string; initials: string };

/**
 * Company detail page split into 5 tabs. Tabs are lazy + cumulative:
 * only the active tab's props are eager, the others land via partial
 * reload on first visit.
 */
const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsAvailableYears: number[];
    billingYear: number;
    pendingDeclarations: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    pendingInvoices: App.Data.User.Billing.PendingInvoiceYearData[];

    // Lazy: populated after the matching tab is first visited.
    options?: { drivers: DriverOption[] };
    contracts?: App.Data.User.Contract.PaginatedContractListData;
    contractsStats?: App.Data.User.Company.CompanyContractsStatsData;
    companyFiscal?: App.Data.User.Company.CompanyFiscalYearData;
    companyBilling?: App.Data.User.Billing.MonthlyBillingBreakdownData;
    companyRentalDiscounts?: App.Data.User.RentalDiscount.RentalDiscountListItemData[];
    declarationLifecycle?: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
}>();

const { activeTab, setTab, loadingTab } = useCompanyTabs();

const fiscalHasTodo = computed<boolean>(
    () => props.pendingDeclarations.length > 0,
);

const billingHasTodo = computed<boolean>(
    () => props.pendingInvoices.length > 0,
);

/**
 * Navigate to the Fiscal tab focused on a given year through the unified
 * `?tab=fiscal&year=Y` query string.
 */
function handleGotoFiscalYear(year: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'fiscal');
    url.searchParams.set('year', String(year));
    router.visit(url.pathname + '?' + url.searchParams.toString(), {
        preserveScroll: true,
    });
}

/**
 * Navigate to the Billing tab focused on a given year.
 */
function handleGotoBillingYear(year: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'billing');
    url.searchParams.set('year', String(year));
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

            <CompanyTabsNav
                :active-tab="activeTab"
                :fiscal-has-todo="fiscalHasTodo"
                :billing-has-todo="billingHasTodo"
                @change="setTab"
            />

            <CompanyOverviewTab
                v-if="activeTab === 'overview'"
                :company="props.company"
                :pending-declarations="props.pendingDeclarations"
                :pending-invoices="props.pendingInvoices"
                @goto-fiscal-year="handleGotoFiscalYear"
                @goto-billing-year="handleGotoBillingYear"
            />

            <!--
                `loadingTab !== '<key>'` forces a fresh remount during a
                partial reload, otherwise inner year selectors keep their
                stale initial bindings after new props land.
            -->
            <template v-else-if="activeTab === 'contracts'">
                <CompanyContractsTab
                    v-if="props.contracts && props.contractsStats && loadingTab !== 'contracts'"
                    :company="props.company"
                    :contracts="props.contracts"
                    :contracts-query="props.contractsQuery"
                    :contracts-stats="props.contractsStats"
                    :contracts-available-years="props.contractsAvailableYears"
                />
                <TabLoadingSkeleton v-else />
            </template>

            <template v-else-if="activeTab === 'drivers'">
                <CompanyDriversTab
                    v-if="props.options && loadingTab !== 'drivers'"
                    :company-id="props.company.id"
                    :company-legal-name="props.company.legalName"
                    :drivers="props.company.drivers"
                    :available-drivers="props.options.drivers"
                />
                <TabLoadingSkeleton v-else />
            </template>

            <template v-else-if="activeTab === 'fiscal'">
                <CompanyFiscalTab
                    v-if="props.companyFiscal && props.declarationLifecycle && loadingTab !== 'fiscal'"
                    :fiscal="props.companyFiscal"
                    :company-id="props.company.id"
                    :declaration-lifecycle="props.declarationLifecycle"
                    :pending-declarations="props.pendingDeclarations"
                />
                <TabLoadingSkeleton v-else />
            </template>

            <template v-else-if="activeTab === 'billing'">
                <CompanyBillingTab
                    v-if="props.companyBilling && loadingTab !== 'billing'"
                    :company-id="props.company.id"
                    :monthly-billing="props.companyBilling"
                    :available-years="props.contractsAvailableYears"
                    :active-year="props.billingYear"
                    :pending-invoices="props.pendingInvoices"
                    :company-rental-discounts="props.companyRentalDiscounts"
                />
                <TabLoadingSkeleton v-else />
            </template>
        </div>
    </UserLayout>
</template>

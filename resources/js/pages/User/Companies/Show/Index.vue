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

// D5.10.V · chargement lazy + cumulatif des onglets. Seules les props
// de l'onglet actif au mount sont eager (cf. `CompanyController::show`)
// · les autres sont `undefined` jusqu'au premier partial reload
// déclenché par `useCompanyTabs.setTab(...)`. Les guards `v-if` ci-bas
// affichent `<TabLoadingSkeleton/>` pendant le partial reload.
const props = defineProps<{
    // Eager · toujours présentes.
    company: App.Data.User.Company.CompanyDetailData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsAvailableYears: number[];
    billingYear: number;
    pendingDeclarations: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    pendingInvoices: App.Data.User.Billing.PendingInvoiceYearData[];

    // Lazy · présentes uniquement après visite de leur onglet respectif.
    options?: { drivers: DriverOption[] };
    contracts?: App.Data.User.Contract.PaginatedContractListData;
    contractsStats?: App.Data.User.Company.CompanyContractsStatsData;
    companyFiscal?: App.Data.User.Company.CompanyFiscalYearData;
    companyBilling?: App.Data.User.Billing.MonthlyBillingBreakdownData;
    declarationLifecycle?: App.Data.User.FiscalDeclaration.DeclarationLifecycleStateData;
}>();

const { activeTab, setTab, loadingTab } = useCompanyTabs();

/**
 * Phase 12 D5.9.D · `true` quand au moins une déclaration de
 * l'entreprise est en attente d'action (lifecycle ≠ GeneratedActive).
 * Sert au dot discret de l'onglet « Fiscalité » dans `CompanyTabsNav`.
 */
const fiscalHasTodo = computed<boolean>(
    () => props.pendingDeclarations.length > 0,
);

/**
 * D5.10.U · idem pour l'onglet « Facturation » quand au moins une
 * facture est à générer pour cette entreprise.
 */
const billingHasTodo = computed<boolean>(
    () => props.pendingInvoices.length > 0,
);

/**
 * D5.10.U · Navigation depuis l'alerte « À faire » vers l'onglet
 * Fiscalité de l'année concernée. URL `?tab=fiscal&year=Y` pilote
 * `useCompanyTabs` (lit `?tab=`) + `useCompanyFiscalSelectedYear`
 * (lit `?year=` unifié).
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
 * D5.10.U · Navigation depuis l'alerte « N factures à générer pour YYYY »
 * vers l'onglet Facturation de l'année concernée · même param `?year=`
 * unifié.
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
                D5.10.V · `loadingTab !== '<key>'` force le skeleton
                pendant un partial reload même si les props précédentes
                sont déjà chargées (onglet stale). Sans cela, le tab
                rendrait avec les ANCIENS props pendant la latence du
                reload · les composables internes (sélecteurs d'année)
                initialisés sur les vieux props ne re-synchroniseraient
                pas après l'arrivée des nouveaux (cf. bug pills désync).
                Le skeleton garantit un remount frais avec les bons props.
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
                />
                <TabLoadingSkeleton v-else />
            </template>
        </div>
    </UserLayout>
</template>

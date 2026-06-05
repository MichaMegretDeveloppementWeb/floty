<script setup lang="ts">
/**
 * Overview tab on the Company detail page: pending alert banner,
 * present-tense KPI row, yearly history, activity exploration card,
 * with Contact and Address side cards on xl+.
 */
import { computed } from 'vue';
import CompanyActivityCard from './overview/CompanyActivityCard.vue';
import CompanyAddressCard from './overview/CompanyAddressCard.vue';
import CompanyContactCard from './overview/CompanyContactCard.vue';
import CompanyKpiCards from './overview/CompanyKpiCards.vue';
import CompanyYearHistoryCard from './overview/CompanyYearHistoryCard.vue';
import PendingActionsAlert from './overview/PendingActionsAlert.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    overview: App.Data.User.Company.CompanyOverviewData;
    pendingDeclarations?: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    pendingInvoices?: App.Data.User.Billing.PendingInvoiceYearData[];
}>();

const emit = defineEmits<{
    'goto-fiscal-year': [year: number];
    'goto-billing-year': [year: number];
}>();

const hasAnyPending = computed<boolean>(
    () => (props.pendingDeclarations?.length ?? 0) + (props.pendingInvoices?.length ?? 0) > 0,
);
</script>

<template>
    <div class="flex flex-col gap-10">
        <PendingActionsAlert
            v-if="hasAnyPending"
            :pending-declarations="props.pendingDeclarations ?? []"
            :pending-invoices="props.pendingInvoices ?? []"
            :company-id="props.company.id"
            @goto-fiscal-year="(year) => emit('goto-fiscal-year', year)"
            @goto-billing-year="(year) => emit('goto-billing-year', year)"
        />

        <CompanyKpiCards
            :kpi-stats="overview.kpiStats"
            :kpi-year="overview.kpiYear"
            :kpi-fiscal-available="overview.kpiFiscalAvailable"
        />

        <div class="grid grid-cols-1 gap-10 xl:grid-cols-3 xl:gap-12">
            <div class="flex flex-col gap-10 xl:col-span-2">
                <section class="flex flex-col gap-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Historique par année
                    </p>
                    <CompanyYearHistoryCard :history="overview.history" unwrapped />
                </section>

                <CompanyActivityCard
                    :activity-by-year="overview.activityByYear"
                    :year-scope="overview.yearScope"
                />

                <!-- Below xl, Contact + Address sit in the main flow; from xl on the aside owns them. -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:hidden">
                    <CompanyContactCard :company="company" />
                    <CompanyAddressCard :company="company" />
                </div>
            </div>

            <aside class="hidden xl:col-span-1 xl:block">
                <div class="flex flex-col gap-6">
                    <CompanyContactCard :company="company" />
                    <CompanyAddressCard :company="company" />
                </div>
            </aside>
        </div>
    </div>
</template>

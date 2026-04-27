<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import CompaniesTable from './partials/CompaniesTable.vue';
import EmptyCompaniesState from './partials/EmptyCompaniesState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    companies: App.Data.User.Company.CompanyListItemData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();
</script>

<template>
    <Head title="Entreprises" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <EmptyCompaniesState v-if="props.companies.length === 0" />
            <CompaniesTable
                v-else
                :companies="props.companies"
                :fiscal-year="fiscalYear"
            />
        </div>
    </UserLayout>
</template>

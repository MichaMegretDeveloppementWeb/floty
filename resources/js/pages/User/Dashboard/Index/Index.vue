<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import KpisGrid from './partials/KpisGrid.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

const props = defineProps<{
    stats: App.Data.User.Dashboard.DashboardStatsData;
}>();

const { currentYear: fiscalYear } = useFiscalYear();
</script>

<template>
    <Head title="Tableau de bord" />

    <UserLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :fiscal-year="fiscalYear" />
            <KpisGrid :stats="props.stats" :fiscal-year="fiscalYear" />
            <QuickLinksGrid />
        </div>
    </UserLayout>
</template>

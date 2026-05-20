<script setup lang="ts">
/** Dashboard 2-column row orchestrating the Declarations + Invoices panels. */
import PendingDeclarationsPanel from './PendingDeclarationsPanel.vue';
import PendingInvoicesPanel from './PendingInvoicesPanel.vue';

defineProps<{
    tasks: App.Data.User.Dashboard.DashboardPendingTasksData;
}>();
</script>

<template>
    <div class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-2">
        <PendingDeclarationsPanel
            :count="tasks.pendingDeclarationsCount"
            :items="tasks.pendingDeclarations"
        />
        <PendingInvoicesPanel
            :monthly-total="tasks.pendingInvoicesMonthlyTotal"
            :items="tasks.pendingInvoices"
        />
    </div>
</template>

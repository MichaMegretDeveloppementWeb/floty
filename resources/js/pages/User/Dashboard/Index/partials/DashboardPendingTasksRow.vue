<script setup lang="ts">
/**
 * Ligne « tâches en attente » du Dashboard (Phase 13 D5.15).
 *
 * Container 2 colonnes orchestrant les panels Déclarations et
 * Factures · chaque panel est autonome (header, liste top 5, état
 * vide, lien « voir les autres »).
 */
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
            :count="tasks.pendingInvoicesCount"
            :items="tasks.pendingInvoices"
        />
    </div>
</template>

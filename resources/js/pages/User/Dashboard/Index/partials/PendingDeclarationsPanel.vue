<script setup lang="ts">
/** Dashboard panel listing up to 5 pending declarations sorted by urgency. */
import { FileClock, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import CompanyYearPickerModal from '@/Components/Domain/Company/CompanyYearPickerModal.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import PendingDeclarationRow from './PendingDeclarationRow.vue';

type Tasks = App.Data.User.Dashboard.DashboardPendingTasksData;

defineProps<{
    count: Tasks['pendingDeclarationsCount'];
    items: Tasks['pendingDeclarations'];
}>();

const pickerOpen = ref<boolean>(false);
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
    >
        <header class="flex flex-wrap items-center gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
            >
                <FileClock :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Déclarations en attente
                </p>
                <p class="text-sm text-slate-500">
                    <template v-if="count > 0">
                        <span class="font-mono font-medium tabular-nums text-slate-900">
                            {{ count }}
                        </span>
                        à soumettre à la DGFiP
                    </template>
                    <template v-else>
                        À soumettre à la DGFiP
                    </template>
                </p>
            </div>
            <Button
                size="sm"
                variant="secondary"
                class="shrink-0"
                @click="pickerOpen = true"
            >
                <template #icon-left>
                    <Plus :size="13" :stroke-width="1.75" />
                </template>
                Préparer une déclaration
            </Button>
        </header>

        <ul v-if="items.length > 0" class="flex flex-col gap-2">
            <li v-for="item in items" :key="`${item.companyId}-${item.fiscalYear}`">
                <PendingDeclarationRow :item="item" />
            </li>
        </ul>

        <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
            Rien à générer pour l'instant. Les déclarations d'un exercice
            sont à préparer à partir du 1er janvier suivant.
        </p>

        <CompanyYearPickerModal
            v-model:open="pickerOpen"
            target="fiscal"
            default-year="previous"
            title="Préparer une déclaration"
            description="Choisissez l'entreprise et l'exercice à déclarer."
            submit-label="Ouvrir la fiscalité"
            year-label="Année fiscale"
        />
    </article>
</template>

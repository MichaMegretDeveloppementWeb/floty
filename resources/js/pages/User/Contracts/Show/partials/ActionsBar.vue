<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { useContractActions } from '@/Composables/Contract/Show/useContractActions';

const props = defineProps<{
    contractId: number;
}>();

const {
    confirmOpen,
    submitting,
    goEdit,
    requestDelete,
    cancelDelete,
    confirmDelete,
} = useContractActions(props.contractId);
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-6"
    >
        <h2 class="text-base font-semibold text-slate-900">Actions</h2>
        <div class="flex flex-wrap gap-2">
            <Button variant="secondary" @click="goEdit">
                <template #icon-left>
                    <Pencil :size="14" :stroke-width="1.75" />
                </template>
                Modifier
            </Button>
            <Button variant="destructive" @click="requestDelete">
                <template #icon-left>
                    <Trash2 :size="14" :stroke-width="1.75" />
                </template>
                Supprimer
            </Button>
        </div>

        <ConfirmModal
            v-model:open="confirmOpen"
            title="Supprimer ce contrat ?"
            message="La plage redevient disponible. La suppression est un soft-delete : les déclarations fiscales déjà émises ne sont pas modifiées."
            confirm-label="Supprimer"
            tone="danger"
            :loading="submitting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </section>
</template>

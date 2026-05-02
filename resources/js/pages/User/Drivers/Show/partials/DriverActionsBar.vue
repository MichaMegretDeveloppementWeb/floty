<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    destroy as destroyRoute,
    edit as editRoute,
} from '@/routes/user/drivers';

const props = defineProps<{
    driverId: number;
    driverFullName: string;
    canDelete: boolean;
}>();

const confirmOpen = ref(false);
const submitting = ref(false);

function requestDelete(): void {
    confirmOpen.value = true;
}

function cancelDelete(): void {
    confirmOpen.value = false;
}

function confirmDelete(): void {
    submitting.value = true;
    router.delete(destroyRoute(props.driverId).url, {
        onFinish: () => {
            submitting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-6"
    >
        <h2 class="text-base font-semibold text-slate-900">Actions</h2>
        <div class="flex flex-wrap gap-2">
            <Link :href="editRoute(props.driverId).url">
                <Button variant="secondary">
                    <template #icon-left>
                        <Pencil :size="14" :stroke-width="1.75" />
                    </template>
                    Modifier
                </Button>
            </Link>
            <Button
                v-if="props.canDelete"
                variant="destructive"
                @click="requestDelete"
            >
                <template #icon-left>
                    <Trash2 :size="14" :stroke-width="1.75" />
                </template>
                Supprimer
            </Button>
        </div>
        <p v-if="!props.canDelete" class="text-xs text-slate-500">
            Suppression désactivée tant que le conducteur a des contrats.
        </p>

        <ConfirmModal
            v-model:open="confirmOpen"
            :title="`Supprimer ${props.driverFullName} ?`"
            message="Le conducteur sera retiré de la liste mais reste consultable dans l'historique des contrats où il a déjà été affecté. Soft delete réversible."
            confirm-label="Supprimer"
            tone="danger"
            :loading="submitting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </section>
</template>

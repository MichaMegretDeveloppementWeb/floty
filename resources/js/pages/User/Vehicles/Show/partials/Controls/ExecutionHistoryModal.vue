<script setup lang="ts">
/**
 * Historique des exécutions d'un contrôle (Chantier B / B2), chargé à
 * l'ouverture. Affiche date, note et documents téléchargeables. Logique dans
 * useControlExecutionHistory.
 */
import { FileText } from 'lucide-vue-next';
import { watch } from 'vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useControlExecutionHistory } from '@/Composables/Control/Vehicle/useControlExecutionHistory';
import { download as downloadRoute } from '@/routes/user/vehicles/controls/documents';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;

const props = defineProps<{
    vehicleId: number;
    control: EffectiveControl | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { entries, loading, error, load } = useControlExecutionHistory(props.vehicleId);

watch(open, (isOpen) => {
    if (isOpen && props.control !== null) {
        void load(props.control);
    }
});
</script>

<template>
    <Modal
        v-model:open="open"
        size="md"
        :title="control ? `Historique · ${control.name}` : 'Historique'"
    >
        <p v-if="loading" class="text-sm text-slate-500">Chargement de l'historique...</p>
        <p v-else-if="error" class="text-sm text-rose-600">Impossible de charger l'historique.</p>
        <p v-else-if="entries.length === 0" class="text-sm text-slate-500 italic">
            Aucune exécution enregistrée pour ce contrôle.
        </p>

        <ol v-else class="flex flex-col gap-3">
            <li
                v-for="entry in entries"
                :key="entry.id"
                class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-sm font-semibold text-slate-900">
                        {{ formatDateFr(entry.executedOn) }}
                    </span>
                </div>
                <p v-if="entry.note" class="text-sm whitespace-pre-line text-slate-600">{{ entry.note }}</p>
                <ul v-if="entry.documents.length > 0" class="flex flex-col gap-1">
                    <li v-for="document in entry.documents" :key="document.id">
                        <a
                            :href="downloadRoute.url({ vehicle: props.vehicleId, document: document.id })"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 text-sm text-blue-700 transition-colors duration-[120ms] hover:text-blue-800"
                        >
                            <FileText :size="14" :stroke-width="1.75" />
                            {{ document.filename }}
                        </a>
                    </li>
                </ul>
            </li>
        </ol>
    </Modal>
</template>

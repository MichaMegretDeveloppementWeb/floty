<script setup lang="ts">
import { Check, History, Minus, Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useCurrentFiscalCharacteristicsCard } from '@/Composables/Vehicle/Show/useCurrentFiscalCharacteristicsCard';
import { useVfcCreateModalState } from '@/Composables/Vehicle/Show/useVfcCreateForm';
import { useVfcDeleteModalState } from '@/Composables/Vehicle/Show/useVfcDeleteForm';
import { useVfcEditModalState } from '@/Composables/Vehicle/Show/useVfcEditForm';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import FiscalHistoryTimeline from './FiscalHistoryTimeline.vue';
import VfcCreateModal from './VfcCreateModal.vue';
import VfcDeleteConfirmModal from './VfcDeleteConfirmModal.vue';
import VfcEditModal from './VfcEditModal.vue';

const props = defineProps<{
    vehicleId: number;
    fiscal: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData | null;
    history: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData[];
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const { historyOpen, historyCount, stats, applicableOptions } =
    useCurrentFiscalCharacteristicsCard(props);

const createState = useVfcCreateModalState();
const editState = useVfcEditModalState();
const deleteState = useVfcDeleteModalState();
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Caractéristiques fiscales actives
                    </h2>
                    <p v-if="props.fiscal" class="mt-0.5 text-xs text-slate-500">
                        Effective depuis le
                        {{ formatDateFr(props.fiscal.effectiveFrom) }}
                    </p>
                </div>
                <Button
                    v-if="historyCount > 0"
                    variant="ghost"
                    size="sm"
                    @click="historyOpen = true"
                >
                    <template #icon-left>
                        <History :size="14" :stroke-width="1.75" />
                    </template>
                    Historique ({{ historyCount }})
                </Button>
            </div>
        </template>

        <p
            v-if="!props.fiscal"
            class="text-sm text-slate-500 italic"
        >
            Aucune version fiscale active pour ce véhicule.
        </p>

        <div
            v-else
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
        >
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-1.5 rounded-lg bg-slate-50/70 px-3 py-3"
            >
                <p
                    class="text-xs font-medium tracking-wide text-slate-400 uppercase"
                >
                    {{ stat.label }}
                </p>
                <p class="text-base font-semibold text-slate-900">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <section
            v-if="props.fiscal && applicableOptions.length > 0"
            class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4"
        >
            <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">
                Exonérations, abattements et usages spéciaux
            </p>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="option in applicableOptions"
                    :key="option.label"
                    :class="[
                        'flex items-start gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors duration-[120ms]',
                        option.active
                            ? 'border-blue-100 bg-blue-50/40 text-slate-900'
                            : 'border-slate-100 bg-slate-50/40 text-slate-500',
                    ]"
                >
                    <span
                        :class="[
                            'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full',
                            option.active
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-slate-200 text-slate-400',
                        ]"
                        :aria-label="option.active ? 'Activé' : 'Non activé'"
                    >
                        <Check v-if="option.active" :size="11" :stroke-width="2.25" />
                        <Minus v-else :size="11" :stroke-width="2.25" />
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span
                            :class="['text-sm font-medium', option.active ? 'text-slate-900' : 'text-slate-600']"
                        >
                            {{ option.label }}
                        </span>
                        <span class="text-xs leading-snug text-slate-500">
                            {{ option.hint }}
                        </span>
                    </span>
                </li>
            </ul>
        </section>

        <Modal
            v-model:open="historyOpen"
            title="Historique des caractéristiques fiscales"
            :description="`${historyCount} version${historyCount > 1 ? 's' : ''} enregistrée${historyCount > 1 ? 's' : ''}, de la plus récente à la plus ancienne.`"
            size="lg"
        >
            <div class="mb-4 flex justify-end">
                <Button
                    variant="primary"
                    size="sm"
                    @click="createState.requestCreate"
                >
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter une entrée
                </Button>
            </div>
            <FiscalHistoryTimeline
                :history="props.history"
                @edit="editState.requestEdit"
                @delete="deleteState.requestDelete"
            />
        </Modal>

        <VfcCreateModal
            v-model:open="createState.open.value"
            :history="props.history"
            :current="props.fiscal"
            :options="props.options"
            :vehicle-id="props.vehicleId"
        />

        <VfcEditModal
            v-model:open="editState.open.value"
            :editing="editState.editing.value"
            :history="props.history"
            :options="props.options"
        />

        <VfcDeleteConfirmModal
            v-model:open="deleteState.open.value"
            :deleting="deleteState.deleting.value"
        />
    </Card>
</template>

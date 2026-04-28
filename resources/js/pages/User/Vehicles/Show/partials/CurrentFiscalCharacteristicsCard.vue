<script setup lang="ts">
import { History } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useCurrentFiscalCharacteristicsCard } from '@/Composables/Vehicle/Show/useCurrentFiscalCharacteristicsCard';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import FiscalHistoryTimeline from './FiscalHistoryTimeline.vue';

const props = defineProps<{
    fiscal: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData | null;
    history: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData[];
}>();

const { historyOpen, historyCount, stats, advancedFlags } =
    useCurrentFiscalCharacteristicsCard(props);
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
            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
        >
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-1 rounded-lg bg-slate-50/70 px-3 py-2.5"
            >
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    {{ stat.label }}
                </p>
                <p class="text-sm font-semibold text-slate-900">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <div
            v-if="props.fiscal && advancedFlags.length > 0"
            class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3"
        >
            <Badge
                v-for="flag in advancedFlags"
                :key="flag"
                tone="blue"
            >
                {{ flag }}
            </Badge>
        </div>

        <Modal
            v-model:open="historyOpen"
            title="Historique des caractéristiques fiscales"
            :description="`${historyCount} version${historyCount > 1 ? 's' : ''} enregistrée${historyCount > 1 ? 's' : ''} — de la plus récente à la plus ancienne.`"
            size="lg"
        >
            <FiscalHistoryTimeline :history="props.history" />
        </Modal>
    </Card>
</template>

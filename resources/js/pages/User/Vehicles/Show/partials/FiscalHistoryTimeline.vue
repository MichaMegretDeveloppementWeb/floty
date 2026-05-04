<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useFiscalHistoryTimeline } from '@/Composables/Vehicle/Show/useFiscalHistoryTimeline';
import {
    energySourceLabel,
    fiscalCharacteristicsChangeReasonLabel,
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

const props = defineProps<{
    history: Vfc[];
}>();

const emit = defineEmits<{
    edit: [vfc: Vfc];
    delete: [vfc: Vfc];
}>();

const { formatPeriod } = useFiscalHistoryTimeline();

const co2OrPa = (item: Vfc): string => {
    if (item.co2Wltp !== null) {
        return `${item.co2Wltp} g/km`;
    }

    if (item.co2Nedc !== null) {
        return `${item.co2Nedc} g/km`;
    }

    if (item.taxableHorsepower !== null) {
        return `${item.taxableHorsepower} CV`;
    }

    return '';
};
</script>

<template>
    <ol
        v-if="props.history.length > 0"
        class="flex flex-col gap-3"
    >
        <li
            v-for="item in props.history"
            :key="item.id"
            :class="[
                'rounded-xl border p-4 transition-colors',
                item.isCurrent
                    ? 'border-emerald-200 bg-emerald-50/30'
                    : 'border-slate-200 bg-white',
            ]"
        >
            <!-- En-tête : période à gauche, actions toujours à droite -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900">
                            {{ formatPeriod(item) }}
                        </span>
                        <Badge v-if="item.isCurrent" tone="emerald">
                            Courante
                        </Badge>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ fiscalCharacteristicsChangeReasonLabel[item.changeReason] }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Modifier cette version"
                        @click="emit('edit', item)"
                    >
                        <Pencil :size="14" :stroke-width="1.75" />
                        <span class="sr-only">Modifier</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Supprimer cette version"
                        @click="emit('delete', item)"
                    >
                        <Trash2 :size="14" :stroke-width="1.75" />
                        <span class="sr-only">Supprimer</span>
                    </Button>
                </div>
            </div>

            <!-- Note explicative (motif « Autre changement ») -->
            <p
                v-if="item.changeNote"
                class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs whitespace-pre-line text-slate-600"
            >
                {{ item.changeNote }}
            </p>

            <!-- Caractéristiques fiscales clés - toujours 4 colonnes -->
            <dl
                class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-xs sm:grid-cols-4"
            >
                <div class="flex flex-col gap-0.5">
                    <dt class="text-slate-400">Énergie</dt>
                    <dd class="font-medium text-slate-700">
                        {{ energySourceLabel[item.energySource] }}
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-slate-400">Homologation</dt>
                    <dd class="font-medium text-slate-700">
                        {{ homologationMethodLabel[item.homologationMethod] }}
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-slate-400">CO₂ / PA</dt>
                    <dd class="font-medium text-slate-700">
                        {{ co2OrPa(item) }}
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-slate-400">Polluants</dt>
                    <dd class="font-medium text-slate-700">
                        {{ pollutantCategoryLabel[item.pollutantCategory] }}
                    </dd>
                </div>
            </dl>
        </li>
    </ol>

    <p v-else class="text-sm text-slate-500 italic">
        Aucun historique fiscal pour ce véhicule.
    </p>
</template>

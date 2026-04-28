<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { useFiscalHistoryTimeline } from '@/Composables/Vehicle/Show/useFiscalHistoryTimeline';
import {
    energySourceLabel,
    fiscalCharacteristicsChangeReasonLabel,
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    history: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData[];
}>();

const { formatPeriod } = useFiscalHistoryTimeline();
</script>

<template>
    <ol
        v-if="props.history.length > 0"
        class="flex flex-col gap-4 border-l-2 border-slate-200 pl-5"
    >
        <li
            v-for="item in props.history"
            :key="item.id"
            class="relative"
        >
            <span
                :class="[
                    'absolute -left-[27px] top-1.5 inline-block h-3 w-3 rounded-full border-2 border-white',
                    item.isCurrent ? 'bg-emerald-500' : 'bg-slate-300',
                ]"
                aria-hidden="true"
            />
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-slate-900">
                    {{ formatPeriod(item) }}
                </span>
                <Badge v-if="item.isCurrent" tone="emerald">Courante</Badge>
                <Badge tone="slate">
                    {{ fiscalCharacteristicsChangeReasonLabel[item.changeReason] }}
                </Badge>
            </div>
            <p
                v-if="item.changeNote"
                class="mt-1 text-sm whitespace-pre-line text-slate-600"
            >
                {{ item.changeNote }}
            </p>
            <dl
                class="mt-2 grid grid-cols-2 gap-x-6 gap-y-4 text-xs text-slate-500 sm:grid-cols-4"
            >
                <div>
                    <dt class="text-slate-400">Énergie</dt>
                    <dd class="text-slate-700">
                        {{ energySourceLabel[item.energySource] }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-400">Méthode</dt>
                    <dd class="text-slate-700">
                        {{ homologationMethodLabel[item.homologationMethod] }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-400">CO₂ / PA</dt>
                    <dd class="text-slate-700">
                        {{
                            item.co2Wltp ??
                            item.co2Nedc ??
                            item.taxableHorsepower ??
                            '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-400">Polluants</dt>
                    <dd class="text-slate-700">
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

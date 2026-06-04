<script setup lang="ts">
import { Download, FileText, ImageIcon } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import { vehicleEventTypeLabel } from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicleEvent: VehicleEvent;
    periodLabel: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Détails
                </h2>
            </template>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs font-medium text-slate-500">Type</dt>
                    <dd class="text-sm text-slate-900">
                        {{ vehicleEventTypeLabel[props.vehicleEvent.type] }}
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs font-medium text-slate-500">Période</dt>
                    <dd class="text-sm text-slate-900">
                        {{ props.periodLabel }}
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs font-medium text-slate-500">Durée</dt>
                    <dd class="text-sm text-slate-900">
                        <template v-if="props.vehicleEvent.daysCount > 0">
                            {{ props.vehicleEvent.daysCount }}
                            jour{{ props.vehicleEvent.daysCount > 1 ? 's' : '' }}
                        </template>
                        <template v-else>
                            En cours
                        </template>
                    </dd>
                </div>
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs font-medium text-slate-500">Effet fiscal</dt>
                    <dd class="text-sm text-slate-900">
                        {{ props.vehicleEvent.hasFiscalImpact ? 'Réduit le prorata fiscal' : 'Aucun effet fiscal' }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card v-if="props.vehicleEvent.description">
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Description
                </h2>
            </template>
            <p class="text-sm whitespace-pre-line text-slate-700">
                {{ props.vehicleEvent.description }}
            </p>
        </Card>

        <Card>
            <template #header>
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">
                        Documents liés
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ props.vehicleEvent.documents.length }}
                    </p>
                </div>
            </template>

            <p
                v-if="props.vehicleEvent.documents.length === 0"
                class="text-sm text-slate-500 italic"
            >
                Aucun document lié.
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="doc in props.vehicleEvent.documents"
                    :key="doc.id"
                    class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600"
                        aria-hidden="true"
                    >
                        <ImageIcon v-if="doc.isImage" :size="16" :stroke-width="1.75" />
                        <FileText v-else :size="16" :stroke-width="1.75" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900">
                            {{ doc.filename }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ doc.sizeFormatted }}
                        </p>
                    </div>
                    <a
                        :href="doc.downloadUrl"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-900"
                        :title="`Télécharger ${doc.filename}`"
                        :aria-label="`Télécharger ${doc.filename}`"
                    >
                        <Download :size="14" :stroke-width="1.75" />
                    </a>
                </li>
            </ul>
        </Card>
    </div>
</template>

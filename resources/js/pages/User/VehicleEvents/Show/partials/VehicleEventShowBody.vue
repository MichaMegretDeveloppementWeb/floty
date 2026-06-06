<script setup lang="ts">
import { ArrowRight, Download, FileText, ImageIcon } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import type { VehicleEventStatus } from '@/Composables/VehicleEvent/Show/useVehicleEventShow';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicleEvent: VehicleEvent;
    isOngoing: boolean;
    startLabel: string;
    endLabel: string | null;
    durationLabel: string;
    categories: string[];
    fiscalStatus: VehicleEventStatus;
    availabilityStatus: VehicleEventStatus;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Période · visual date range -->
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-5">
            <p class="eyebrow mb-4">
                Période
            </p>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-4">
                <div class="flex-1">
                    <p class="text-[11px] font-medium tracking-wide text-slate-400 uppercase">
                        Début
                    </p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">
                        {{ props.startLabel }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span class="hidden h-px w-6 bg-slate-200 sm:block" aria-hidden="true" />
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        <ArrowRight :size="13" :stroke-width="1.75" class="text-slate-400" aria-hidden="true" />
                        {{ props.durationLabel }}
                    </span>
                    <span class="hidden h-px w-6 bg-slate-200 sm:block" aria-hidden="true" />
                </div>

                <div class="flex-1 sm:text-right">
                    <p class="text-[11px] font-medium tracking-wide text-slate-400 uppercase">
                        Fin
                    </p>
                    <p
                        v-if="!props.isOngoing"
                        class="mt-1 text-lg font-semibold text-slate-900"
                    >
                        {{ props.endLabel }}
                    </p>
                    <p v-else class="mt-1 text-lg font-medium text-slate-400 italic">
                        Non définie
                    </p>
                </div>
            </div>
        </div>

        <!-- Montant · cost attached to the event (carnet de dépenses) -->
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-5">
            <p class="eyebrow mb-2">
                Montant
            </p>
            <p
                v-if="props.vehicleEvent.amountCents !== null"
                class="font-mono text-lg font-semibold text-slate-900"
            >
                {{ formatEur(props.vehicleEvent.amountCents / 100, 2) }}
            </p>
            <p v-else class="text-sm text-slate-400 italic">
                Aucun montant renseigné.
            </p>
        </div>

        <!-- Nature · category / fiscal / availability -->
        <div class="grid grid-cols-1 overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-3">
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0">
                <p class="eyebrow mb-2">
                    Catégories
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <Badge
                        v-for="cat in props.categories"
                        :key="cat"
                        tone="slate"
                        :uppercase="false"
                    >
                        {{ cat }}
                    </Badge>
                </div>
            </div>
            <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0">
                <p class="eyebrow mb-2">
                    Effet fiscal
                </p>
                <Badge :tone="props.fiscalStatus.tone" :uppercase="false">
                    {{ props.fiscalStatus.value }}
                </Badge>
                <p class="mt-2 text-[11px] leading-snug text-slate-500">
                    {{ props.fiscalStatus.caption }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="eyebrow mb-2">
                    Disponibilité
                </p>
                <Badge :tone="props.availabilityStatus.tone" :uppercase="false">
                    {{ props.availabilityStatus.value }}
                </Badge>
                <p class="mt-2 text-[11px] leading-snug text-slate-500">
                    {{ props.availabilityStatus.caption }}
                </p>
            </div>
        </div>

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

            <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <a
                    v-for="doc in props.vehicleEvent.documents"
                    :key="doc.id"
                    :href="doc.downloadUrl"
                    class="group flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50"
                    :title="`Télécharger ${doc.filename}`"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600"
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
                    <Download
                        :size="15"
                        :stroke-width="1.75"
                        class="shrink-0 text-slate-400 transition-colors duration-[120ms] group-hover:text-slate-700"
                        aria-hidden="true"
                    />
                </a>
            </div>
        </Card>
    </div>
</template>

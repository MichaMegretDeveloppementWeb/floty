<script setup lang="ts">
/**
 * "Événements" tab content: a real vertical timeline with one dot per
 * calendar day. A multi-day event appears on each of its days ("jour X/N");
 * several events the same day are listed under that day's dot. Each day
 * offers a "+ Ajouter sur ce jour" shortcut pre-filling that date, and each
 * event links to its detail page. Unfolding and labels are computed by
 * {@see useVehicleEventsTimeline}; the year / period filter (client-side, the
 * events are already loaded) by {@see useVehicleEventsTimelineFilter}, with
 * the same year ↔ period UX as the Contracts Index.
 */
import { Link } from '@inertiajs/vue3';
import { Ban, CalendarDays, Plus, TrendingDown } from 'lucide-vue-next';
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FlagIcon from '@/Components/Ui/FlagIcon.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import {
    formatVehicleEventDaySpan,
    useVehicleEventsTimeline,
} from '@/Composables/Vehicle/Show/useVehicleEventsTimeline';
import { useVehicleEventsTimelineFilter } from '@/Composables/Vehicle/Show/useVehicleEventsTimelineFilter';
import { create as createRoute, show as showRoute } from '@/routes/user/vehicles/events';
import { formatDayLongFr } from '@/Utils/format/formatDayLongFr';
import { formatEur } from '@/Utils/format/formatEur';
import { vehicleEventDisplayTitle } from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicleId: number;
    vehicleEvents: VehicleEvent[];
    /** Backend current calendar year, upper bound of the year options. */
    currentYear: number;
}>();

const {
    scopeMode,
    setScopeMode,
    yearOptions,
    yearModel,
    periodRange,
    periodOngoing,
    pickerYear,
    periodLabel,
    periodPopoverOpen,
    popoverRoot,
    isFiltered,
    activeWindow,
    filteredEvents,
} = useVehicleEventsTimelineFilter(() => props.vehicleEvents, props.currentYear);

const days = useVehicleEventsTimeline(filteredEvents, activeWindow);

const hasAnyEvent = computed<boolean>(() => props.vehicleEvents.length > 0);
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Événements
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ filteredEvents.length }} événement{{ filteredEvents.length > 1 ? 's' : '' }}
                        <template v-if="isFiltered">
                            sur {{ props.vehicleEvents.length }}
                        </template>
                        <template v-else>
                            enregistré{{ filteredEvents.length > 1 ? 's' : '' }}
                        </template>
                    </p>
                </div>
                <Link :href="createRoute.url({ vehicle: props.vehicleId })">
                    <Button size="sm">
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Ajouter
                    </Button>
                </Link>
            </div>
        </template>

        <p
            v-if="!hasAnyEvent"
            class="text-sm text-slate-500 italic"
        >
            Aucun événement enregistré pour ce véhicule.
        </p>

        <template v-else>
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <div
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white p-1 shadow-sm"
                    role="tablist"
                    aria-label="Mode de filtre temporel"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="scopeMode === 'year'"
                        :class="[
                            'rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[120ms]',
                            scopeMode === 'year'
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-50',
                        ]"
                        @click="setScopeMode('year')"
                    >
                        Année
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="scopeMode === 'period'"
                        :class="[
                            'rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[120ms]',
                            scopeMode === 'period'
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-50',
                        ]"
                        @click="setScopeMode('period')"
                    >
                        Période personnalisée
                    </button>
                </div>

                <InlineYearSelector
                    v-if="scopeMode === 'year'"
                    id="vehicle-events-year"
                    v-model="yearModel"
                    label="Exercice"
                    :options="yearOptions"
                />

                <div v-else ref="popoverRoot" class="relative">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white py-1.5 pr-3 pl-3 text-sm font-semibold text-slate-900 shadow-sm transition-colors duration-[120ms] ease-out hover:border-slate-400 focus-visible:ring-2 focus-visible:ring-blue-200 focus-visible:outline-none"
                        :aria-expanded="periodPopoverOpen"
                        @click="periodPopoverOpen = !periodPopoverOpen"
                    >
                        <CalendarDays
                            :size="14"
                            :stroke-width="1.75"
                            class="shrink-0 text-slate-500"
                            aria-hidden="true"
                        />
                        <span class="text-slate-700">Période :</span>
                        <span>{{ periodLabel }}</span>
                    </button>

                    <div
                        v-if="periodPopoverOpen"
                        class="fixed inset-0 z-40 bg-slate-900/20 sm:hidden"
                        aria-hidden="true"
                        @click="periodPopoverOpen = false"
                    />
                    <div
                        v-if="periodPopoverOpen"
                        class="fixed inset-x-4 bottom-4 z-50 flex max-h-[80vh] flex-col rounded-lg border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:bottom-auto sm:left-auto sm:mt-2 sm:max-h-[calc(100vh-8rem)] sm:w-[360px] sm:max-w-[calc(100vw-2rem)] sm:shadow-lg"
                    >
                        <div class="flex flex-col gap-3 overflow-y-auto p-4">
                            <DateRangePicker
                                id="vehicle-events-period"
                                v-model:range="periodRange"
                                v-model:ongoing="periodOngoing"
                                :year="pickerYear"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-if="days.length === 0"
                class="text-sm text-slate-500 italic"
            >
                Aucun événement sur cette période.
            </p>

            <ol v-else class="flex flex-col">
                <li
                    v-for="(day, index) in days"
                    :key="day.date"
                    class="relative flex items-start gap-3 pl-2"
                >
                    <span
                        v-if="index < days.length - 1"
                        class="absolute top-2 left-[13px] z-0 h-full w-px bg-slate-200"
                        aria-hidden="true"
                    />
                    <span
                        class="relative z-10 mt-1 inline-block size-2.5 shrink-0 rounded-full border-2 border-slate-300 bg-white"
                        aria-hidden="true"
                    />

                    <div class="flex min-w-0 flex-1 flex-col pb-7">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                                {{ formatDayLongFr(day.date) }}
                            </h3>
                            <Link
                                :href="createRoute.url({ vehicle: props.vehicleId }, { query: { date: day.date } })"
                                class="inline-flex items-center gap-1 text-xs font-medium text-slate-400 transition-colors duration-[120ms] hover:text-slate-700"
                            >
                                <Plus :size="13" :stroke-width="1.75" />
                                Ajouter sur ce jour
                            </Link>
                        </div>

                        <ul class="mt-2.5 flex flex-col gap-1.5">
                            <li
                                v-for="entry in day.entries"
                                :key="entry.event.id"
                            >
                                <Link
                                    :href="showRoute.url({ vehicle: props.vehicleId, vehicleEvent: entry.event.id })"
                                    class="-mx-2 flex gap-2.5 rounded-lg px-2 py-1.5 transition-colors duration-[120ms] hover:bg-slate-50"
                                >
                                    <span
                                        class="mt-[7px] size-1.5 shrink-0 rounded-full bg-slate-300"
                                        aria-hidden="true"
                                    />
                                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-[15px] leading-tight font-semibold text-slate-900">
                                                {{ vehicleEventDisplayTitle(entry.event) }}
                                            </span>
                                            <span
                                                v-if="formatVehicleEventDaySpan(entry)"
                                                class="font-mono text-[11px] text-slate-400"
                                            >
                                                {{ formatVehicleEventDaySpan(entry) }}
                                            </span>
                                            <span class="ml-4.5 flex flex-wrap items-center gap-1.5">
                                                <Badge
                                                    v-for="cat in entry.event.categories"
                                                    :key="cat"
                                                    tone="slate"
                                                    :uppercase="false"
                                                >
                                                    {{ cat }}
                                                </Badge>
                                                <FlagIcon
                                                    v-if="entry.event.impliesUnavailability"
                                                    :icon="Ban"
                                                    tone="amber"
                                                    label="Génère une indisponibilité"
                                                    :size="15"
                                                />
                                                <FlagIcon
                                                    v-if="entry.event.hasFiscalImpact"
                                                    :icon="TrendingDown"
                                                    tone="rose"
                                                    label="Fiscalement réducteur"
                                                    :size="15"
                                                />
                                                <span
                                                    v-if="entry.event.amountCents !== null"
                                                    class="font-mono text-xs font-medium text-slate-600"
                                                >
                                                    {{ formatEur(entry.event.amountCents / 100, 2) }}
                                                </span>
                                            </span>
                                        </div>
                                        <p
                                            v-if="entry.event.description"
                                            class="line-clamp-1 text-xs whitespace-pre-line text-slate-500"
                                        >
                                            {{ entry.event.description }}
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </li>
            </ol>
        </template>
    </Card>
</template>

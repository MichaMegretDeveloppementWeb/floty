<script setup lang="ts">
/**
 * "Événements" tab content: a real vertical timeline with one dot per
 * calendar day. A multi-day event appears on each of its days ("jour X/N");
 * several events the same day are listed under that day's dot. Each day
 * offers a "+ Ajouter sur ce jour" shortcut pre-filling that date, and each
 * event links to its detail page. Unfolding and labels are computed by
 * {@see useVehicleEventsTimeline}.
 */
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import {
    formatVehicleEventDaySpan,
    useVehicleEventsTimeline,
} from '@/Composables/Vehicle/Show/useVehicleEventsTimeline';
import { create as createRoute, show as showRoute } from '@/routes/user/vehicles/events';
import { formatDayLongFr } from '@/Utils/format/formatDayLongFr';
import {
    vehicleEventDisplayCategory,
    vehicleEventDisplayTitle,
} from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicleId: number;
    vehicleEvents: VehicleEvent[];
}>();

const days = useVehicleEventsTimeline(() => props.vehicleEvents);
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
                        {{ props.vehicleEvents.length }} événement{{ props.vehicleEvents.length > 1 ? 's' : '' }}
                        enregistré{{ props.vehicleEvents.length > 1 ? 's' : '' }}
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
            v-if="days.length === 0"
            class="text-sm text-slate-500 italic"
        >
            Aucun événement enregistré pour ce véhicule.
        </p>

        <ol v-else class="flex flex-col">
            <li
                v-for="(day, index) in days"
                :key="day.date"
                class="relative flex items-start gap-3 pl-2"
            >
                <span
                    v-if="index < days.length - 1"
                    class="absolute top-3 left-[13px] z-0 h-full w-px bg-slate-200"
                    aria-hidden="true"
                />
                <span
                    class="relative z-10 mt-1.5 inline-block size-2.5 shrink-0 rounded-full bg-slate-300 ring-2 ring-slate-100"
                    aria-hidden="true"
                />

                <div class="flex flex-1 flex-col gap-2 pb-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ formatDayLongFr(day.date) }}
                        </h3>
                        <Link
                            :href="createRoute.url({ vehicle: props.vehicleId }, { query: { date: day.date } })"
                            class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition-colors duration-[120ms] hover:text-slate-900"
                        >
                            <Plus :size="13" :stroke-width="1.75" />
                            Ajouter sur ce jour
                        </Link>
                    </div>

                    <ul class="flex flex-col gap-0.5">
                        <li
                            v-for="entry in day.entries"
                            :key="entry.event.id"
                        >
                            <Link
                                :href="showRoute.url({ vehicle: props.vehicleId, vehicleEvent: entry.event.id })"
                                class="-mx-2 block rounded-md px-2 py-1.5 transition-colors duration-[120ms] hover:bg-slate-50"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="size-1.5 shrink-0 rounded-full bg-slate-300"
                                        aria-hidden="true"
                                    />
                                    <span class="text-sm font-medium text-slate-900">
                                        {{ vehicleEventDisplayTitle(entry.event) }}
                                    </span>
                                    <Badge tone="slate">
                                        {{ vehicleEventDisplayCategory(entry.event) }}
                                    </Badge>
                                    <Badge v-if="entry.event.impliesUnavailability" tone="amber">
                                        Indisponibilité
                                    </Badge>
                                    <Badge v-if="entry.event.hasFiscalImpact" tone="rose">
                                        Réducteur fiscal
                                    </Badge>
                                </div>
                                <p
                                    v-if="formatVehicleEventDaySpan(entry)"
                                    class="mt-0.5 font-mono text-xs text-slate-500"
                                >
                                    {{ formatVehicleEventDaySpan(entry) }}
                                </p>
                                <p
                                    v-if="entry.event.description"
                                    class="mt-0.5 line-clamp-2 text-xs whitespace-pre-line text-slate-600"
                                >
                                    {{ entry.event.description }}
                                </p>
                            </Link>
                        </li>
                    </ul>
                </div>
            </li>
        </ol>
    </Card>
</template>

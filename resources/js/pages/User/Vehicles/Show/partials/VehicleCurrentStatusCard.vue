<script setup lang="ts">
/**
 * "État actuel" card at the top of the overview tab: what is happening to the
 * vehicle right now (today). Shows the ongoing rental(s) with their drivers
 * and the ongoing event(s). A vehicle out of the fleet shows its exit instead;
 * an active vehicle with nothing in progress shows a neutral availability line.
 */
import { Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarRange,
    CircleCheck,
    LogOut,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import { show as vehicleEventShowRoute } from '@/routes/user/vehicles/events';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { contractTypeLabel } from '@/Utils/labels/contractEnumLabels';
import { vehicleEventDisplayTitle } from '@/Utils/labels/vehicleEventEnumLabels';

const props = defineProps<{
    status: App.Data.User.Vehicle.CurrentVehicleStatusData;
    vehicleId: number;
    isExited: boolean;
    exitDate: string | null;
}>();

const hasActivity = computed<boolean>(
    () => props.status.rentals.length > 0 || props.status.events.length > 0,
);

function eventPeriod(event: App.Data.User.VehicleEvent.VehicleEventData): string {
    return event.endDate
        ? `du ${formatDateFr(event.startDate)} au ${formatDateFr(event.endDate)}`
        : `depuis le ${formatDateFr(event.startDate)}`;
}
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-sm font-medium tracking-wide text-slate-500 uppercase">
                État actuel
            </h2>
        </template>

        <!-- Out of the fleet: no current operational state. -->
        <div
            v-if="isExited"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
        >
            <LogOut :size="18" :stroke-width="1.75" class="shrink-0 text-slate-400" aria-hidden="true" />
            <span>
                Véhicule sorti de la flotte<template v-if="exitDate">
                    le {{ formatDateFr(exitDate) }}</template
                >.
            </span>
        </div>

        <div v-else-if="hasActivity" class="flex flex-col gap-5">
            <!-- Ongoing rental(s) -->
            <section v-if="status.rentals.length" class="flex flex-col gap-2">
                <h3 class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Location en cours
                </h3>
                <Link
                    v-for="rental in status.rentals"
                    :key="rental.id"
                    :href="contractsShowRoute.url(rental.id)"
                    class="group flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                >
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <CompanyTag
                            :name="rental.companyLegalName"
                            :initials="rental.companyShortCode"
                            :color="rental.companyColor"
                        />
                        <span
                            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                        >
                            {{ contractTypeLabel[rental.contractType] }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-sm text-slate-500">
                            <CalendarRange :size="15" :stroke-width="1.75" aria-hidden="true" />
                            du {{ formatDateFr(rental.startDate) }} au
                            {{ formatDateFr(rental.endDate) }}
                        </span>
                    </div>
                    <div v-if="rental.drivers.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="driver in rental.drivers"
                            :key="driver.id"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700"
                        >
                            <span
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-semibold text-blue-700"
                            >
                                {{ driver.initials }}
                            </span>
                            {{ driver.fullName }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-slate-400">
                        Aucun conducteur attribué
                    </span>
                </Link>
            </section>

            <!-- Ongoing event(s) -->
            <section v-if="status.events.length" class="flex flex-col gap-2">
                <h3 class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Événement en cours
                </h3>
                <Link
                    v-for="event in status.events"
                    :key="event.id"
                    :href="
                        vehicleEventShowRoute.url({
                            vehicle: vehicleId,
                            vehicleEvent: event.id,
                        })
                    "
                    class="group flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                >
                    <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-900">
                        <CalendarClock :size="16" :stroke-width="1.75" class="text-slate-400" aria-hidden="true" />
                        {{ vehicleEventDisplayTitle(event) }}
                    </span>
                    <span class="text-sm text-slate-500">{{ eventPeriod(event) }}</span>
                </Link>
            </section>
        </div>

        <!-- Active vehicle, nothing in progress today. -->
        <div v-else class="flex items-center gap-3 text-sm text-slate-500">
            <CircleCheck :size="18" :stroke-width="1.75" class="shrink-0 text-slate-400" aria-hidden="true" />
            <span>Aucune location ni événement en cours. Véhicule disponible aujourd'hui.</span>
        </div>
    </Card>
</template>

<script setup lang="ts">
/**
 * "État actuel" card at the top of the overview tab: what is happening to the
 * vehicle right now (today). Two symmetric panels, Location | Événement: each
 * shows its ongoing item(s) or stays as a muted, dashed placeholder when there
 * is none (the two dimensions are independent, a vehicle can be rented AND have
 * an event at once). A vehicle out of the fleet shows its exit instead.
 */
import { Link } from '@inertiajs/vue3';
import { CalendarClock, CalendarRange, FileText, LogOut } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import { show as vehicleEventShowRoute } from '@/routes/user/vehicles/events';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    contractTypeLabel,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';
import { vehicleEventDisplayTitle } from '@/Utils/labels/vehicleEventEnumLabels';

defineProps<{
    status: App.Data.User.Vehicle.CurrentVehicleStatusData;
    vehicleId: number;
    isExited: boolean;
    exitDate: string | null;
}>();

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

        <!-- Out of the fleet: no current operational state, single banner. -->
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

        <!-- Active: two symmetric panels. -->
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Location panel -->
            <section
                :class="[
                    'flex flex-col rounded-xl border p-4',
                    status.rentals.length
                        ? 'border-slate-200 bg-white'
                        : 'border-dashed border-slate-200 bg-slate-50/60',
                ]"
            >
                <header
                    :class="[
                        'mb-3 flex items-center gap-2 text-xs font-medium tracking-wider uppercase',
                        status.rentals.length ? 'text-slate-500' : 'text-slate-400',
                    ]"
                >
                    <FileText :size="14" :stroke-width="1.75" aria-hidden="true" />
                    Location
                </header>

                <div v-if="status.rentals.length" class="flex flex-col gap-3">
                    <Link
                        v-for="rental in status.rentals"
                        :key="rental.id"
                        :href="contractsShowRoute.url(rental.id)"
                        class="group -mx-2 flex flex-col gap-2 rounded-lg px-2 py-1.5 transition-colors duration-[120ms] ease-out hover:bg-slate-50"
                    >
                        <CompanyTag
                            :name="rental.companyLegalName"
                            :initials="rental.companyShortCode"
                            :color="rental.companyColor"
                        />
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                            <span
                                class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600"
                                :title="contractTypeLabel[rental.contractType]"
                            >
                                {{ contractTypeShortLabel[rental.contractType] }}
                            </span>
                            <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                <CalendarRange :size="14" :stroke-width="1.75" aria-hidden="true" />
                                {{ formatDateFr(rental.startDate) }} →
                                {{ formatDateFr(rental.endDate) }}
                            </span>
                        </div>
                        <div v-if="rental.drivers.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="driver in rental.drivers"
                                :key="driver.id"
                                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-700"
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
                </div>
                <div v-else class="flex flex-1 items-center">
                    <p class="text-sm text-slate-400">Aucune location en cours</p>
                </div>
            </section>

            <!-- Événement panel -->
            <section
                :class="[
                    'flex flex-col rounded-xl border p-4',
                    status.events.length
                        ? 'border-slate-200 bg-white'
                        : 'border-dashed border-slate-200 bg-slate-50/60',
                ]"
            >
                <header
                    :class="[
                        'mb-3 flex items-center gap-2 text-xs font-medium tracking-wider uppercase',
                        status.events.length ? 'text-slate-500' : 'text-slate-400',
                    ]"
                >
                    <CalendarClock :size="14" :stroke-width="1.75" aria-hidden="true" />
                    Événement
                </header>

                <div v-if="status.events.length" class="flex flex-col gap-3">
                    <Link
                        v-for="event in status.events"
                        :key="event.id"
                        :href="
                            vehicleEventShowRoute.url({
                                vehicle: vehicleId,
                                vehicleEvent: event.id,
                            })
                        "
                        class="group -mx-2 flex flex-col gap-1 rounded-lg px-2 py-1.5 transition-colors duration-[120ms] ease-out hover:bg-slate-50"
                    >
                        <span class="text-sm font-medium text-slate-900">
                            {{ vehicleEventDisplayTitle(event) }}
                        </span>
                        <span class="text-sm text-slate-500">{{ eventPeriod(event) }}</span>
                    </Link>
                </div>
                <div v-else class="flex flex-1 items-center">
                    <p class="text-sm text-slate-400">Aucun événement en cours</p>
                </div>
            </section>
        </div>
    </Card>
</template>

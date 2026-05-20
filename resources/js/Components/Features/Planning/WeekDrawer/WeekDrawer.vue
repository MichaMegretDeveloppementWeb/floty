<script setup lang="ts">
/**
 * "Week detail" drawer opened from a heatmap cell.
 * Renders the vehicle/week header, 7 day slots, current-week companies list
 * and a contract creation form with live fiscal preview.
 */
import { computed, ref, watch } from 'vue';
import { CELLS_PER_YEAR } from '@/Utils/Date/isoWeeks';
import CompaniesOnWeekList from './partials/CompaniesOnWeekList.vue';
import ContractForm from './partials/ContractForm.vue';
import DrawerHeader from './partials/DrawerHeader.vue';
import WeekDayGrid from './partials/WeekDayGrid.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type WeekData = App.Data.User.Planning.PlanningWeekData;
type DateRange = { startDate: string | null; endDate: string | null };

const props = withDefaults(
    defineProps<{
        open: boolean;
        week: WeekData | null;
        companies: Company[];
        fiscalYear: number;
        /** Company-locked mode (drawer opened from the per-company view). */
        lockedCompany?: Company | null;
    }>(),
    {
        lockedCompany: null,
    },
);

defineEmits<{
    close: [];
    'contracts-created': [];
}>();

const selectedCompanyId = ref<number | null>(null);
const selectedRange = ref<DateRange>({ startDate: null, endDate: null });

// Reset on each open; pre-select the locked company when applicable.
watch(
    () => props.week,
    () => {
        selectedCompanyId.value = props.lockedCompany?.id ?? null;
        selectedRange.value = { startDate: null, endDate: null };
    },
);

// Starting month of the drawer calendar (Thursday of the week, clamped to Jan/Dec on edges).
const startMonth = computed((): number => {
    if (!props.week) {
        return 1;
    }

    const weekNumber = props.week.weekNumber;

    if (weekNumber === 1) {
        return 1;
    }

    if (weekNumber === CELLS_PER_YEAR) {
        return 12;
    }

    // Month of Thursday (4th ISO day).
    const thursday = props.week.days[3]?.date;
    if (thursday === undefined) {
        return Number(props.week.weekStart.slice(5, 7));
    }

    return Number(thursday.slice(5, 7));
});

// Year-wide busy dates for this vehicle (grey-out in the picker).
const disabledDates = computed((): string[] =>
    props.week ? props.week.vehicleBusyDates : [],
);

// Dates of the displayed week (visually highlighted in the calendar).
const weekDates = computed((): string[] =>
    props.week ? props.week.days.map((d) => d.date) : [],
);

// Selected days within the displayed week (read-only highlight).
const selectedDatesInWeek = computed((): string[] => {
    const start = selectedRange.value.startDate;
    const end = selectedRange.value.endDate;

    if (start === null || end === null) {
        return [];
    }

    return weekDates.value.filter((date) => date >= start && date <= end);
});
</script>

<template>
    <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-out"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-slate-900/30"
            aria-hidden="true"
            @click="$emit('close')"
        />
    </transition>

    <transition
        enter-active-class="transition-transform duration-200 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-150 ease-out"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <aside
            v-if="open && week"
            class="fixed inset-y-0 right-0 z-50 flex w-full flex-col overflow-y-auto bg-white shadow-2xl md:w-[480px]"
            role="dialog"
            :aria-label="`Semaine ${week.weekNumber} · ${week.licensePlate}`"
        >
            <DrawerHeader
                :week-number="week.weekNumber"
                :fiscal-year="fiscalYear"
                :license-plate="week.licensePlate"
                :week-start="week.weekStart"
                :week-end="week.weekEnd"
                @close="$emit('close')"
            />

            <div class="flex flex-col gap-6 px-5 py-5">
                <WeekDayGrid
                    :days="week.days"
                    :selected-dates="selectedDatesInWeek"
                />

                <CompaniesOnWeekList :entries="week.companiesOnWeek" />

                <ContractForm
                    :vehicle-id="week.vehicleId"
                    :companies="companies"
                    :fiscal-year="fiscalYear"
                    :start-month="startMonth"
                    :week-dates="weekDates"
                    :disabled-dates="disabledDates"
                    :selected-company-id="selectedCompanyId"
                    :selected-range="selectedRange"
                    :locked-company="lockedCompany"
                    @update:selected-company-id="selectedCompanyId = $event"
                    @update:selected-range="selectedRange = $event"
                    @submitted="$emit('contracts-created')"
                />
            </div>
        </aside>
    </transition>
</template>

<script setup lang="ts">
/**
 * Drawer « détail de semaine » (CDC § 3.7).
 *
 * S'ouvre au clic sur une cellule de la heatmap. Contient :
 *   - L'en-tête véhicule + semaine
 *   - 7 slots (Lun → Dim) montrant l'attribution du jour (ou « libre »)
 *   - Liste des entreprises présentes sur cette semaine
 *   - Un formulaire d'attribution + preview taxes induites
 *
 * Logique métier extraite dans les composables `useFiscalPreview` /
 * `useApi` ; rendu décomposé en 4 partials sous `partials/`.
 */
import { computed, ref, watch } from 'vue';
import AssignmentForm from './partials/AssignmentForm.vue';
import CompaniesOnWeekList from './partials/CompaniesOnWeekList.vue';
import DrawerHeader from './partials/DrawerHeader.vue';
import WeekDayGrid from './partials/WeekDayGrid.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type WeekData = App.Data.User.Planning.PlanningWeekData;

const props = defineProps<{
    open: boolean;
    week: WeekData | null;
    companies: Company[];
    fiscalYear: number;
}>();

defineEmits<{
    close: [];
    'assignments-created': [];
}>();

const selectedCompanyId = ref<number | null>(null);
const selectedDates = ref<string[]>([]);

// Reset à chaque ouverture.
watch(
    () => props.week,
    () => {
        selectedCompanyId.value = null;
        selectedDates.value = [];
    },
);

const startMonth = computed((): number =>
    props.week ? Number(props.week.weekStart.slice(5, 7)) : 1,
);

// Dates déjà occupées par ce véhicule — à griser dans le picker.
const disabledDates = computed((): string[] =>
    props.week
        ? props.week.days
              .filter((d) => d.assignment !== null)
              .map((d) => d.date)
        : [],
);

// Dates de la semaine — repérées visuellement dans le calendrier.
const weekDates = computed((): string[] =>
    props.week ? props.week.days.map((d) => d.date) : [],
);

function toggleSlot(date: string): void {
    const set = new Set(selectedDates.value);

    if (set.has(date)) {
        set.delete(date);
    } else {
        set.add(date);
    }

    selectedDates.value = [...set].sort();
}
</script>

<template>
    <!-- Overlay -->
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

    <!-- Drawer latéral -->
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
                    :selected-dates="selectedDates"
                    @toggle-slot="toggleSlot"
                />

                <CompaniesOnWeekList :entries="week.companiesOnWeek" />

                <AssignmentForm
                    :vehicle-id="week.vehicleId"
                    :companies="companies"
                    :fiscal-year="fiscalYear"
                    :start-month="startMonth"
                    :week-dates="weekDates"
                    :disabled-dates="disabledDates"
                    :selected-company-id="selectedCompanyId"
                    :selected-dates="selectedDates"
                    @update:selected-company-id="selectedCompanyId = $event"
                    @update:selected-dates="selectedDates = $event"
                    @submitted="$emit('assignments-created')"
                />
            </div>
        </aside>
    </transition>
</template>

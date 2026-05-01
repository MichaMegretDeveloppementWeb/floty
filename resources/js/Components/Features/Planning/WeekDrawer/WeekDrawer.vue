<script setup lang="ts">
/**
 * Drawer « détail de semaine » (CDC § 3.7).
 *
 * S'ouvre au clic sur une cellule de la heatmap. Contient :
 *   - L'en-tête véhicule + semaine
 *   - 7 slots (Lun → Dim) montrant le contrat actif du jour (ou « libre »)
 *   - Liste des entreprises présentes sur cette semaine
 *   - Un formulaire de création de contrat (sélection plage début/fin)
 *     + preview des taxes induites (R-2024-021 LCD per-contract).
 */
import { computed, ref, watch } from 'vue';
import CompaniesOnWeekList from './partials/CompaniesOnWeekList.vue';
import ContractForm from './partials/ContractForm.vue';
import DrawerHeader from './partials/DrawerHeader.vue';
import WeekDayGrid from './partials/WeekDayGrid.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type WeekData = App.Data.User.Planning.PlanningWeekData;
type DateRange = { startDate: string | null; endDate: string | null };

const props = defineProps<{
    open: boolean;
    week: WeekData | null;
    companies: Company[];
    fiscalYear: number;
}>();

defineEmits<{
    close: [];
    'contracts-created': [];
}>();

const selectedCompanyId = ref<number | null>(null);
const selectedRange = ref<DateRange>({ startDate: null, endDate: null });

// Reset à chaque ouverture.
watch(
    () => props.week,
    () => {
        selectedCompanyId.value = null;
        selectedRange.value = { startDate: null, endDate: null };
    },
);

const startMonth = computed((): number =>
    props.week ? Number(props.week.weekStart.slice(5, 7)) : 1,
);

// Dates déjà occupées par ce véhicule sur toute l'année — à griser
// dans le picker. Auparavant le filtre se limitait aux 7 jours de la
// semaine affichée, ce qui laissait l'utilisateur sélectionner une
// plage qui chevauchait un contrat hors de cette semaine. Le backend
// fournit désormais l'index complet via `vehicleBusyDates`.
const disabledDates = computed((): string[] =>
    props.week ? props.week.vehicleBusyDates : [],
);

// Dates de la semaine — repérées visuellement dans le calendrier.
const weekDates = computed((): string[] =>
    props.week ? props.week.days.map((d) => d.date) : [],
);

// Dates de la semaine couvertes par la plage sélectionnée — affichage
// visuel des cases pré-sélectionnées dans la grille semaine (lecture
// seule, la sélection se fait via le DateRangePicker du formulaire).
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
                    @update:selected-company-id="selectedCompanyId = $event"
                    @update:selected-range="selectedRange = $event"
                    @submitted="$emit('contracts-created')"
                />
            </div>
        </aside>
    </transition>
</template>

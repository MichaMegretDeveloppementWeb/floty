<script setup lang="ts">
/**
 * Grille « État de la semaine » du drawer Planning - purement
 * indicative depuis la refonte attribution → contrat. La saisie de
 * plage se fait au DateRangePicker du formulaire, pas ici. Les slots
 * jour ne portent donc plus de cursor / hover / handler clic.
 */
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';

type WeekData = App.Data.User.Planning.PlanningWeekData;

defineProps<{
    days: WeekData['days'];
    selectedDates: string[];
}>();

const dayLongLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

function isSelected(date: string, selected: string[]): boolean {
    return selected.includes(date);
}
</script>

<template>
    <section>
        <p class="eyebrow mb-2">État de la semaine</p>
        <div class="grid grid-cols-7 gap-1">
            <div
                v-for="(slot, idx) in days"
                :key="slot.date"
                :class="[
                    'flex flex-col items-center gap-0.5 rounded-md p-1.5 text-center text-[10px]',
                    slot.contract
                        ? 'bg-slate-50'
                        : isSelected(slot.date, selectedDates)
                          ? 'bg-blue-600 text-white'
                          : 'border border-dashed border-slate-200',
                    slot.hasUnavailability && 'ring-1 ring-rose-500 ring-inset',
                ]"
                :aria-label="slot.hasUnavailability ? `${slot.dayLabel} : indisponibilité présente` : undefined"
            >
                <span
                    :class="[
                        'font-medium',
                        isSelected(slot.date, selectedDates) && !slot.contract
                            ? 'text-blue-100'
                            : 'text-slate-500',
                    ]"
                >
                    {{ dayLongLabels[idx] }}
                </span>
                <span
                    :class="[
                        'font-mono',
                        isSelected(slot.date, selectedDates) && !slot.contract
                            ? 'text-white'
                            : 'text-slate-400',
                    ]"
                >
                    {{ Number(slot.date.slice(-2)) }}
                </span>
                <CompanyTag
                    v-if="slot.contract"
                    :name="slot.contract.company.shortCode"
                    :initials="slot.contract.company.shortCode"
                    :color="slot.contract.company.color"
                    class="mt-1"
                />
                <span
                    v-else-if="isSelected(slot.date, selectedDates)"
                    class="mt-1 text-[10px] font-medium text-white"
                >
                    sélectionné
                </span>
                <span v-else class="mt-1 text-[10px] text-slate-400">
                    libre
                </span>
            </div>
        </div>
    </section>
</template>

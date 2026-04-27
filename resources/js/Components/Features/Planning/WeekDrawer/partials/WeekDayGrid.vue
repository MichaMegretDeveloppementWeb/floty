<script setup lang="ts">
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';

type WeekData = App.Data.User.Planning.PlanningWeekData;

defineProps<{
    days: WeekData['days'];
    selectedDates: string[];
}>();

const emit = defineEmits<{
    'toggle-slot': [date: string];
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
            <button
                v-for="(slot, idx) in days"
                :key="slot.date"
                type="button"
                :disabled="slot.assignment !== null"
                :class="[
                    'flex flex-col items-center gap-0.5 rounded-md p-1.5 text-center text-[10px] transition-colors duration-[120ms] ease-out',
                    slot.assignment
                        ? 'cursor-not-allowed bg-slate-50'
                        : isSelected(slot.date, selectedDates)
                          ? 'bg-blue-600 text-white hover:bg-blue-700'
                          : 'border border-dashed border-slate-200 hover:border-slate-400 hover:bg-slate-50',
                ]"
                :aria-pressed="isSelected(slot.date, selectedDates)"
                @click="
                    slot.assignment === null && emit('toggle-slot', slot.date)
                "
            >
                <span
                    :class="[
                        'font-medium',
                        isSelected(slot.date, selectedDates) && !slot.assignment
                            ? 'text-blue-100'
                            : 'text-slate-500',
                    ]"
                >
                    {{ dayLongLabels[idx] }}
                </span>
                <span
                    :class="[
                        'font-mono',
                        isSelected(slot.date, selectedDates) && !slot.assignment
                            ? 'text-white'
                            : 'text-slate-400',
                    ]"
                >
                    {{ Number(slot.date.slice(-2)) }}
                </span>
                <CompanyTag
                    v-if="slot.assignment"
                    :name="slot.assignment.company.shortCode"
                    :initials="slot.assignment.company.shortCode.slice(0, 2)"
                    :color="slot.assignment.company.color"
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
            </button>
        </div>
    </section>
</template>

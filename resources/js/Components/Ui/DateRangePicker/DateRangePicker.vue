<script setup lang="ts">
/**
 * Calendrier custom de sélection d'une plage continue de dates.
 *
 * **v2 (04.I.2)** - 3 améliorations UX, API publique conservée :
 *   1. Header avec selects mois + année (±5 ans glissants) + chevrons,
 *      navigation rapide
 *   2. Auto-normalize de l'ordre des clics : peu importe lequel des deux
 *      clics est premier, `start = min(clics)`, `end = max(clics)`
 *   3. Inputs date textuels synchronisés bidirectionnellement avec le
 *      calendrier (input Fin disabled en mode `ongoing`)
 *
 * Toggle « en cours » (`v-model:ongoing`) : désactive la borne de fin
 * et garde uniquement `startDate` (cas d'une indispo dont on ne connaît
 * pas encore la date de retour).
 *
 * `disabledDates` : ISO Y-m-d non sélectionnables (jours déjà attribués).
 * Si une nouvelle plage chevauche un disabledDate → range refusée et
 * `errorMessage` exposé sous le calendrier.
 *
 * Toute la logique vit dans `useDateRangePicker` ; ce .vue est purement
 * présentationnel.
 */
import { ChevronLeft, ChevronRight, Infinity as InfinityIcon, X } from 'lucide-vue-next';
import { toRef } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import {
    useDateRangePicker,
} from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
import type { DateRange } from '@/Composables/Ui/DateRangePicker/useDateRangePicker';

const props = withDefaults(
    defineProps<{
        /** Année initiale d'ouverture du calendrier (centre du select année). */
        year: number;
        /** Mois initial 1..12 (défaut = 1). */
        startMonth?: number;
        /** ISO Y-m-d non sélectionnables. */
        disabledDates?: string[];
        /** ISO Y-m-d à mettre en évidence (anneau bleu) - utilisé pour
         *  marquer la semaine de contexte dans le drawer Planning. */
        highlightDates?: string[];
    }>(),
    {
        startMonth: 1,
        disabledDates: () => [],
        highlightDates: () => [],
    },
);

const range = defineModel<DateRange>('range', { required: true });
const ongoing = defineModel<boolean>('ongoing', { required: true });

const {
    currentYear,
    currentMonth,
    errorMessage,
    monthOptions,
    yearOptions,
    weeks,
    gotoPrevMonth,
    gotoNextMonth,
    onDayClick,
    onStartDateInput,
    onEndDateInput,
    clearSelection,
} = useDateRangePicker(
    toRef(props, 'year'),
    toRef(props, 'startMonth'),
    toRef(props, 'disabledDates'),
    range,
    ongoing,
    toRef(props, 'highlightDates'),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-center gap-1.5">
            <button
                type="button"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100"
                aria-label="Mois précédent"
                @click="gotoPrevMonth"
            >
                <ChevronLeft :size="16" :stroke-width="1.75" />
            </button>

            <select
                v-model="currentMonth"
                aria-label="Sélectionner le mois"
                class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:bg-slate-50 focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]"
            >
                <option
                    v-for="opt in monthOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>

            <select
                v-model="currentYear"
                aria-label="Sélectionner l'année"
                class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:bg-slate-50 focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]"
            >
                <option
                    v-for="opt in yearOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>

            <button
                type="button"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100"
                aria-label="Mois suivant"
                @click="gotoNextMonth"
            >
                <ChevronRight :size="16" :stroke-width="1.75" />
            </button>
        </div>

        <div
            class="grid grid-cols-7 gap-0.5 text-center text-[10px] font-medium text-slate-500 uppercase"
        >
            <span>Lun</span>
            <span>Mar</span>
            <span>Mer</span>
            <span>Jeu</span>
            <span>Ven</span>
            <span>Sam</span>
            <span>Dim</span>
        </div>

        <div class="flex flex-col gap-0.5">
            <div
                v-for="(week, wi) in weeks"
                :key="wi"
                class="grid grid-cols-7 gap-0.5"
            >
                <button
                    v-for="cell in week"
                    :key="cell.iso"
                    type="button"
                    :disabled="cell.disabled"
                    :class="[
                        'relative h-8 rounded-md text-xs transition-colors duration-[120ms] ease-out',
                        cell.disabled
                            ? 'cursor-not-allowed text-slate-300 line-through'
                            : cell.isStart || cell.isEnd
                              ? cell.inMonth
                                ? 'cursor-pointer bg-blue-600 font-medium text-white hover:bg-blue-700'
                                : 'cursor-pointer bg-blue-600 font-medium text-blue-100 hover:bg-blue-700'
                              : cell.isInRange
                                ? cell.inMonth
                                  ? 'cursor-pointer bg-blue-100 font-medium text-blue-900 hover:bg-blue-200'
                                  : 'cursor-pointer bg-blue-100 font-medium text-slate-400 hover:bg-blue-200'
                                : cell.inMonth
                                  ? 'cursor-pointer text-slate-700 hover:bg-slate-100'
                                  : 'cursor-pointer text-slate-300 hover:bg-slate-100',
                        cell.inCurrentWeek && !cell.isStart && !cell.isEnd && !cell.isInRange
                            ? 'ring-2 ring-blue-400 ring-inset'
                            : '',
                    ]"
                    :aria-pressed="cell.isStart || cell.isEnd"
                    @click="onDayClick(cell)"
                >
                    {{ cell.day }}
                    <InfinityIcon
                        v-if="cell.isStart && ongoing"
                        :size="10"
                        :stroke-width="2"
                        class="absolute right-0.5 bottom-0.5"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-end grid-cols-2 gap-2 border-t border-slate-100 pt-2.5 mt-3">
            <label class="flex flex-col gap-1">
                <span class="text-[10px] font-medium tracking-wide text-slate-500 uppercase">
                    Début
                </span>
                <input
                    type="date"
                    :value="range.startDate ?? ''"
                    class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 transition-colors duration-[120ms] ease-out focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)] w-[14em]"
                    @change="(e) => onStartDateInput((e.target as HTMLInputElement).value)"
                />
            </label>
            <label class="flex flex-col gap-1">
                <span
                    :class="[
                        'text-[10px] font-medium tracking-wide uppercase',
                        ongoing ? 'text-slate-300' : 'text-slate-500',
                    ]"
                >
                    Fin
                </span>
                <input
                    type="date"
                    :value="range.endDate ?? ''"
                    :disabled="ongoing"
                    :class="[
                        'rounded-md border px-2 py-1 text-sm transition-colors duration-[120ms] ease-out focus:outline-none w-[14em]',
                        ongoing
                            ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400'
                            : 'border-slate-200 bg-white text-slate-900 focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]',
                    ]"
                    @change="(e) => onEndDateInput((e.target as HTMLInputElement).value)"
                />
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">

            <!-- Bouton secondary visible si sélection active -->
            <Button
                v-if="range.startDate !== null"
                type="button"
                variant="secondary"
                size="sm"
                @click="clearSelection"
            >
                <template #icon-left>
                    <X :size="14" :stroke-width="1.75" />
                </template>
                Effacer
            </Button>
        </div>

        <p
            v-if="errorMessage"
            class="rounded-md bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700"
            role="alert"
        >
            {{ errorMessage }}
        </p>
    </div>
</template>

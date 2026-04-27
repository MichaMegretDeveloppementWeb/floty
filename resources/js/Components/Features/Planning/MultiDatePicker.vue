<script setup lang="ts">
/**
 * Calendrier custom avec sélection multi-dates.
 *
 * Modes :
 * - Clic simple : remplace la sélection par la date unique cliquée
 * - Ctrl+clic   : toggle (ajoute ou retire la date de la sélection)
 * - Shift+clic  : sélectionne la plage entre l'ancre précédente et la
 *                 date cliquée (remplace le contenu des dates dans la plage)
 *
 * Dates dans `disabledDates` (déjà attribuées à une autre entreprise ou
 * indispos) → non cliquables et grisées.
 *
 * Dates dans `currentPairDates` → déjà attribuées à CE couple et mises en
 * évidence (pour montrer le cumul).
 */
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        year: number;
        startMonth?: number; // 1..12
        disabledDates?: string[]; // ISO "YYYY-MM-DD"
        currentPairDates?: string[]; // déjà attribuées au couple courant
        highlightDates?: string[]; // semaine courante du drawer (contour visuel)
    }>(),
    {
        startMonth: 1,
        disabledDates: () => [],
        currentPairDates: () => [],
        highlightDates: () => [],
    },
);

const selected = defineModel<string[]>('selected', { required: true });

const currentMonth = ref<number>(props.startMonth);
const anchorDate = ref<string | null>(null);

const monthLabel = computed((): string => {
    const date = new Date(props.year, currentMonth.value - 1, 1);

    return date.toLocaleDateString('fr-FR', {
        month: 'long',
        year: 'numeric',
    });
});

const disabledSet = computed(() => new Set(props.disabledDates));
const currentPairSet = computed(() => new Set(props.currentPairDates));
const selectedSet = computed(() => new Set(selected.value));
const highlightSet = computed(() => new Set(props.highlightDates));

type DayCell = {
    iso: string;
    day: number;
    inMonth: boolean;
    disabled: boolean;
    selected: boolean;
    existingForPair: boolean;
    highlighted: boolean;
};

const weeks = computed((): DayCell[][] => {
    const year = props.year;
    const month = currentMonth.value - 1; // 0-based

    const firstOfMonth = new Date(year, month, 1);
    const jsDayOfWeek = firstOfMonth.getDay(); // 0=Dim
    // Lundi = 0 dans notre calendrier FR.
    const leading = (jsDayOfWeek + 6) % 7;

    const gridStart = new Date(year, month, 1 - leading);
    const rows: DayCell[][] = [];

    for (let row = 0; row < 6; row++) {
        const week: DayCell[] = [];

        for (let col = 0; col < 7; col++) {
            const d = new Date(
                gridStart.getFullYear(),
                gridStart.getMonth(),
                gridStart.getDate() + row * 7 + col,
            );
            const iso = formatIso(d);
            week.push({
                iso,
                day: d.getDate(),
                inMonth: d.getMonth() === month,
                disabled:
                    disabledSet.value.has(iso) || d.getFullYear() !== year,
                selected: selectedSet.value.has(iso),
                existingForPair: currentPairSet.value.has(iso),
                highlighted: highlightSet.value.has(iso),
            });
        }

        rows.push(week);
        const last = week[6]!.iso;

        if (new Date(last).getMonth() !== month && row >= 4) {
            break;
        }
    }

    return rows;
});

function formatIso(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

function gotoPrevMonth(): void {
    if (currentMonth.value > 1) {
        currentMonth.value -= 1;
    }
}

function gotoNextMonth(): void {
    if (currentMonth.value < 12) {
        currentMonth.value += 1;
    }
}

function onDayClick(cell: DayCell, event: MouseEvent): void {
    if (cell.disabled) {
        return;
    }

    const iso = cell.iso;

    // Shift+clic → sélection de plage entre l'ancre et la date cliquée.
    if (event.shiftKey && anchorDate.value !== null) {
        const range = buildRange(anchorDate.value, iso).filter(
            (d) => !disabledSet.value.has(d),
        );
        const merged = new Set<string>([...selected.value, ...range]);
        selected.value = [...merged].sort();

        return;
    }

    // Ctrl/Cmd+clic → toggle sans reset.
    if (event.ctrlKey || event.metaKey) {
        anchorDate.value = iso;

        if (selectedSet.value.has(iso)) {
            selected.value = selected.value.filter((d) => d !== iso);
        } else {
            selected.value = [...selected.value, iso].sort();
        }

        return;
    }

    // Clic simple → remplace la sélection par cette unique date.
    anchorDate.value = iso;
    selected.value = selectedSet.value.has(iso) ? [] : [iso];
}

function buildRange(startIso: string, endIso: string): string[] {
    const a = new Date(startIso);
    const b = new Date(endIso);
    const start = a <= b ? a : b;
    const end = a <= b ? b : a;
    const result: string[] = [];
    const cur = new Date(start);

    while (cur <= end) {
        result.push(formatIso(cur));
        cur.setDate(cur.getDate() + 1);
    }

    return result;
}

function clearSelection(): void {
    selected.value = [];
    anchorDate.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- En-tête mois + navigation -->
        <div class="flex items-center justify-between">
            <button
                type="button"
                :disabled="currentMonth === 1"
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Mois précédent"
                @click="gotoPrevMonth"
            >
                <ChevronLeft :size="16" :stroke-width="1.75" />
            </button>
            <p class="text-sm font-medium text-slate-900 capitalize">
                {{ monthLabel }}
            </p>
            <button
                type="button"
                :disabled="currentMonth === 12"
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Mois suivant"
                @click="gotoNextMonth"
            >
                <ChevronRight :size="16" :stroke-width="1.75" />
            </button>
        </div>

        <!-- Jours de la semaine -->
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

        <!-- Grille du mois -->
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
                        !cell.inMonth
                            ? 'text-slate-300'
                            : cell.disabled
                              ? 'cursor-not-allowed text-slate-300'
                              : cell.selected
                                ? 'bg-blue-600 font-medium text-white hover:bg-blue-700'
                                : cell.existingForPair
                                  ? 'bg-blue-100 font-medium text-blue-900 hover:bg-blue-200'
                                  : 'text-slate-700 hover:bg-slate-100',
                        cell.highlighted && !cell.selected
                            ? 'ring-1 ring-blue-400 ring-offset-0'
                            : '',
                    ]"
                    :aria-pressed="cell.selected"
                    @click="onDayClick(cell, $event)"
                >
                    {{ cell.day }}
                </button>
            </div>
        </div>

        <!-- Récap + aide -->
        <div
            class="flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs"
        >
            <p class="text-slate-500">
                <span class="font-medium text-slate-900"
                    >{{ selected.length }} jour{{
                        selected.length > 1 ? 's' : ''
                    }}
                    sélectionné{{ selected.length > 1 ? 's' : '' }}</span
                >
                · Shift+clic plage · Ctrl+clic non-successifs
            </p>
            <button
                v-if="selected.length > 0"
                type="button"
                class="text-slate-500 hover:text-slate-900"
                @click="clearSelection"
            >
                Effacer
            </button>
        </div>
    </div>
</template>

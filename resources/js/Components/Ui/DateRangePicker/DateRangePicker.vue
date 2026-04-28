<script setup lang="ts">
/**
 * Calendrier custom de sélection d'une plage continue de dates.
 *
 * Modes :
 * - 1er clic : pose `startDate` et reset `endDate`
 * - 2ᵉ clic :
 *   - si > startDate → pose `endDate`
 *   - si < startDate → re-pose `startDate` (reset)
 *   - si ongoing actif → ignoré (toggle sans fin attendu)
 *
 * Toggle « en cours » (`v-model:ongoing`) : désactive la borne de fin
 * et garde uniquement `startDate` comme référence (cas d'une indispo
 * dont on ne connaît pas encore la date de retour).
 *
 * `disabledDates` : ISO Y-m-d non sélectionnables (jours déjà attribués).
 * Si la plage [start, end] contient une date désactivée → range refusée
 * et `errorMessage` exposé sous le calendrier.
 *
 * Navigation mois libre (pas borné à 1 année) — utile pour les indispos
 * chevauchant la fin d'année.
 */
import { ChevronLeft, ChevronRight, Infinity as InfinityIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type DateRange = {
    startDate: string | null;
    endDate: string | null;
};

const props = withDefaults(
    defineProps<{
        /** Année initiale d'ouverture du calendrier (mois 1). */
        year: number;
        /** Mois initial 1..12 (défaut = mois courant si dans l'année). */
        startMonth?: number;
        /** ISO Y-m-d non sélectionnables. */
        disabledDates?: string[];
    }>(),
    {
        startMonth: 1,
        disabledDates: () => [],
    },
);

const range = defineModel<DateRange>('range', { required: true });
const ongoing = defineModel<boolean>('ongoing', { required: true });

const currentYear = ref<number>(props.year);
const currentMonth = ref<number>(props.startMonth);

const disabledSet = computed<Set<string>>(() => new Set(props.disabledDates));

const errorMessage = ref<string | null>(null);

watch(ongoing, (value) => {
    if (value) {
        // Ongoing activé : on retire la date de fin si présente.
        range.value = { startDate: range.value.startDate, endDate: null };
        errorMessage.value = null;
    }
});

const monthLabel = computed<string>(() => {
    const date = new Date(currentYear.value, currentMonth.value - 1, 1);

    return date.toLocaleDateString('fr-FR', {
        month: 'long',
        year: 'numeric',
    });
});

type DayCell = {
    iso: string;
    day: number;
    inMonth: boolean;
    disabled: boolean;
    isStart: boolean;
    isEnd: boolean;
    isInRange: boolean;
};

const weeks = computed<DayCell[][]>(() => {
    const year = currentYear.value;
    const monthIdx = currentMonth.value - 1;

    const firstOfMonth = new Date(year, monthIdx, 1);
    const jsDayOfWeek = firstOfMonth.getDay(); // 0=Dim
    const leading = (jsDayOfWeek + 6) % 7; // Lundi = 0

    const gridStart = new Date(year, monthIdx, 1 - leading);
    const rows: DayCell[][] = [];

    const start = range.value.startDate;
    const end = range.value.endDate;

    for (let row = 0; row < 6; row++) {
        const week: DayCell[] = [];

        for (let col = 0; col < 7; col++) {
            const d = new Date(
                gridStart.getFullYear(),
                gridStart.getMonth(),
                gridStart.getDate() + row * 7 + col,
            );
            const iso = formatIso(d);
            const isStart = start !== null && iso === start;
            const isEnd = end !== null && iso === end;
            const isInRange =
                start !== null
                && end !== null
                && iso > start
                && iso < end;

            week.push({
                iso,
                day: d.getDate(),
                inMonth: d.getMonth() === monthIdx,
                disabled: disabledSet.value.has(iso),
                isStart,
                isEnd,
                isInRange,
            });
        }

        rows.push(week);

        const last = week[6]!.iso;

        if (new Date(last).getMonth() !== monthIdx && row >= 4) {
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

        return;
    }

    currentMonth.value = 12;
    currentYear.value -= 1;
}

function gotoNextMonth(): void {
    if (currentMonth.value < 12) {
        currentMonth.value += 1;

        return;
    }

    currentMonth.value = 1;
    currentYear.value += 1;
}

/**
 * Vérifie qu'aucune date désactivée n'est dans la plage [start, end]
 * (bornes incluses). Retourne la liste des dates en conflit.
 */
function rangeConflicts(start: string, end: string): string[] {
    const conflicts: string[] = [];
    const a = new Date(start);
    const b = new Date(end);
    const cur = new Date(a);

    while (cur <= b) {
        const iso = formatIso(cur);

        if (disabledSet.value.has(iso)) {
            conflicts.push(iso);
        }

        cur.setDate(cur.getDate() + 1);
    }

    return conflicts;
}

function onDayClick(cell: DayCell): void {
    if (cell.disabled) {
        return;
    }

    const iso = cell.iso;

    // Pas de startDate ou range complète → 1er clic = nouveau start.
    if (
        range.value.startDate === null
        || (range.value.startDate !== null && range.value.endDate !== null)
    ) {
        range.value = { startDate: iso, endDate: null };
        errorMessage.value = null;

        return;
    }

    // Mode ongoing : tout clic supplémentaire ré-ancre juste le start.
    if (ongoing.value) {
        range.value = { startDate: iso, endDate: null };
        errorMessage.value = null;

        return;
    }

    // 2ᵉ clic en mode plage.
    const start = range.value.startDate;

    if (iso < start) {
        // Antérieur au start → on ré-ancre le start.
        range.value = { startDate: iso, endDate: null };
        errorMessage.value = null;

        return;
    }

    if (iso === start) {
        // Même date → on ferme la plage sur 1 jour.
        range.value = { startDate: start, endDate: start };
        errorMessage.value = null;

        return;
    }

    // Plage [start, iso] → vérifier qu'aucune date désactivée n'est dedans.
    const conflicts = rangeConflicts(start, iso);

    if (conflicts.length > 0) {
        const formatted = conflicts.map(formatFr).join(', ');
        errorMessage.value = `Plage refusée : conflit avec ${conflicts.length} jour(s) déjà attribué(s) (${formatted}).`;

        return;
    }

    range.value = { startDate: start, endDate: iso };
    errorMessage.value = null;
}

function formatFr(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

function clearSelection(): void {
    range.value = { startDate: null, endDate: null };
    errorMessage.value = null;
}

const summary = computed<string>(() => {
    const start = range.value.startDate;
    const end = range.value.endDate;

    if (start === null) {
        return 'Aucune sélection';
    }

    if (ongoing.value) {
        return `Depuis le ${formatFr(start)} (en cours)`;
    }

    if (end === null) {
        return `Début : ${formatFr(start)} — sélectionnez la fin`;
    }

    return `Du ${formatFr(start)} au ${formatFr(end)}`;
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100"
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
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100"
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
                        !cell.inMonth
                            ? 'text-slate-300'
                            : cell.disabled
                              ? 'cursor-not-allowed text-slate-300 line-through'
                              : cell.isStart || cell.isEnd
                                ? 'bg-blue-600 font-medium text-white hover:bg-blue-700'
                                : cell.isInRange
                                  ? 'bg-blue-100 font-medium text-blue-900 hover:bg-blue-200'
                                  : 'text-slate-700 hover:bg-slate-100',
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

        <div
            class="flex items-center justify-between border-t border-slate-100 pt-2.5 text-xs"
        >
            <p class="text-slate-700">
                {{ summary }}
            </p>
            <button
                v-if="range.startDate !== null"
                type="button"
                class="text-slate-500 hover:text-slate-900"
                @click="clearSelection"
            >
                Effacer
            </button>
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

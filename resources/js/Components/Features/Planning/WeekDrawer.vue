<script setup lang="ts">
/**
 * Drawer « détail de semaine » (CDC § 3.7).
 *
 * S'ouvre au clic sur une cellule de la heatmap. Contient :
 *   - L'en-tête véhicule + semaine
 *   - 7 slots (Lun → Dim) montrant l'attribution du jour (ou « libre »)
 *   - Liste des entreprises présentes sur cette semaine
 *   - Un formulaire d'attribution : choix entreprise + dates + preview
 *     des taxes induites en temps réel via POST /app/planning/preview-taxes
 */
import Button from '@/Components/Ui/Button/Button.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { postJson } from '@/lib/http';
import { daysInYear } from '@/Utils/date/daysInYear';
import { X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import MultiDatePicker from './MultiDatePicker.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type WeekData = App.Data.User.Planning.PlanningWeekData;
type FiscalPreview = App.Data.User.Fiscal.FiscalPreviewData;

const props = defineProps<{
    open: boolean;
    week: WeekData | null;
    companies: Company[];
    fiscalYear: number;
}>();

const emit = defineEmits<{
    close: [];
    'assignments-created': [];
}>();

const selectedCompanyId = ref<number | null>(null);
const selectedDates = ref<string[]>([]);
const preview = ref<FiscalPreview | null>(null);
const previewLoading = ref(false);
const submitting = ref(false);

// Reset à chaque ouverture du drawer.
watch(
    () => props.week,
    () => {
        selectedCompanyId.value = null;
        selectedDates.value = [];
        preview.value = null;
    },
);

const startMonth = computed((): number => {
    if (!props.week) return 1;
    return Number(props.week.weekStart.slice(5, 7));
});

// Dates déjà occupées par ce véhicule (toutes entreprises) — à griser
// dans le picker pour éviter les conflits.
const disabledDates = computed((): string[] => {
    if (!props.week) return [];
    return props.week.days
        .filter((d) => d.assignment !== null)
        .map((d) => d.date);
});

const currentPairDatesHint = computed((): string[] => {
    // Au MVP on n'affiche que les dates de cette semaine attribuées au
    // couple sélectionné. Pour une vraie vision annuelle il faudrait
    // récupérer toutes les dates côté backend.
    if (!props.week || selectedCompanyId.value === null) return [];
    return props.week.days
        .filter(
            (d) => d.assignment?.company.id === selectedCompanyId.value,
        )
        .map((d) => d.date);
});

// Toutes les dates de la semaine affichée — repérées visuellement dans le
// calendrier pour que l'utilisateur sache où il est.
const weekDates = computed((): string[] => {
    if (!props.week) return [];
    return props.week.days.map((d) => d.date);
});

// Toggle d'un slot jour de la grille de semaine.
function toggleSlot(date: string, isDisabled: boolean): void {
    if (isDisabled) return;
    const set = new Set(selectedDates.value);
    if (set.has(date)) {
        set.delete(date);
    } else {
        set.add(date);
    }
    selectedDates.value = [...set].sort();
}

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: String(c.id),
        label: `${c.shortCode} — ${c.legalName}`,
    })),
);

const companyIdString = computed({
    get: () =>
        selectedCompanyId.value !== null
            ? String(selectedCompanyId.value)
            : '',
    set: (v: string) => {
        selectedCompanyId.value = v === '' ? null : Number(v);
    },
});

// Preview fiscal en temps réel avec debounce simple.
let debounceHandle: number | null = null;
watch([selectedCompanyId, selectedDates], () => {
    if (debounceHandle) window.clearTimeout(debounceHandle);
    debounceHandle = window.setTimeout(() => {
        void fetchPreview();
    }, 200);
});

async function fetchPreview(): Promise<void> {
    if (
        !props.week ||
        selectedCompanyId.value === null ||
        selectedDates.value.length === 0
    ) {
        preview.value = null;
        return;
    }

    previewLoading.value = true;
    try {
        preview.value = await postJson<FiscalPreview>(
            '/app/planning/preview-taxes',
            {
                vehicleId: props.week.vehicleId,
                companyId: selectedCompanyId.value,
                dates: selectedDates.value,
            },
        );
    } catch {
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
}

async function submit(): Promise<void> {
    if (
        !props.week ||
        selectedCompanyId.value === null ||
        selectedDates.value.length === 0
    ) {
        return;
    }

    submitting.value = true;
    try {
        await postJson('/app/planning/assignments', {
            vehicleId: props.week.vehicleId,
            companyId: selectedCompanyId.value,
            dates: selectedDates.value,
        });
        emit('assignments-created');
    } finally {
        submitting.value = false;
    }
}

const formatEur = (value: number): string =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
        .format(value)
        .replace(/ | /g, ' ');

const dayLongLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
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
            @click="emit('close')"
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
            <!-- En-tête -->
            <header
                class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white/95 px-5 py-4 backdrop-blur"
            >
                <div>
                    <p class="eyebrow mb-0.5">
                        Semaine {{ week.weekNumber }} · {{ fiscalYear }}
                    </p>
                    <h2 class="font-mono text-sm font-medium text-slate-900">
                        {{ week.licensePlate }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        Du {{ week.weekStart }} au {{ week.weekEnd }}
                    </p>
                </div>
                <button
                    type="button"
                    class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    aria-label="Fermer"
                    @click="emit('close')"
                >
                    <X :size="16" :stroke-width="1.75" />
                </button>
            </header>

            <!-- Corps -->
            <div class="flex flex-col gap-6 px-5 py-5">
                <!-- 7 jours de la semaine — cliquables pour les jours libres -->
                <section>
                    <p class="eyebrow mb-2">État de la semaine</p>
                    <div class="grid grid-cols-7 gap-1">
                        <button
                            v-for="(slot, idx) in week.days"
                            :key="slot.date"
                            type="button"
                            :disabled="slot.assignment !== null"
                            :class="[
                                'flex flex-col items-center gap-0.5 rounded-md p-1.5 text-center text-[10px] transition-colors duration-[120ms] ease-out',
                                slot.assignment
                                    ? 'cursor-not-allowed bg-slate-50'
                                    : selectedDates.includes(slot.date)
                                      ? 'bg-blue-600 text-white hover:bg-blue-700'
                                      : 'border border-dashed border-slate-200 hover:border-slate-400 hover:bg-slate-50',
                            ]"
                            :aria-pressed="selectedDates.includes(slot.date)"
                            @click="toggleSlot(slot.date, slot.assignment !== null)"
                        >
                            <span
                                :class="[
                                    'font-medium',
                                    selectedDates.includes(slot.date) &&
                                    !slot.assignment
                                        ? 'text-blue-100'
                                        : 'text-slate-500',
                                ]"
                            >
                                {{ dayLongLabels[idx] }}
                            </span>
                            <span
                                :class="[
                                    'font-mono',
                                    selectedDates.includes(slot.date) &&
                                    !slot.assignment
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
                                v-else-if="selectedDates.includes(slot.date)"
                                class="mt-1 text-[10px] font-medium text-white"
                            >
                                sélectionné
                            </span>
                            <span
                                v-else
                                class="mt-1 text-[10px] text-slate-400"
                            >
                                libre
                            </span>
                        </button>
                    </div>
                </section>

                <!-- Entreprises présentes -->
                <section v-if="week.companiesOnWeek.length > 0">
                    <p class="eyebrow mb-2">Entreprises présentes</p>
                    <ul class="flex flex-col gap-1">
                        <li
                            v-for="entry in week.companiesOnWeek"
                            :key="entry.company.id"
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm"
                        >
                            <CompanyTag
                                :name="entry.company.shortCode"
                                :initials="entry.company.shortCode.slice(0, 2)"
                                :color="entry.company.color"
                            />
                            <div class="flex-1 truncate text-slate-700">
                                {{ entry.company.legalName }}
                            </div>
                            <span
                                class="font-mono text-xs text-slate-500"
                            >
                                {{ entry.days }} j
                            </span>
                        </li>
                    </ul>
                </section>

                <!-- Formulaire d'attribution -->
                <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
                    <p class="eyebrow mb-0">Attribuer des jours</p>

                    <SelectInput
                        v-model="companyIdString"
                        label="Entreprise"
                        placeholder="Choisir une entreprise…"
                        :options="companyOptions"
                    />

                    <MultiDatePicker
                        v-model:selected="selectedDates"
                        :year="fiscalYear"
                        :start-month="startMonth"
                        :disabled-dates="disabledDates"
                        :current-pair-dates="currentPairDatesHint"
                        :highlight-dates="weekDates"
                    />

                    <!-- Preview fiscal -->
                    <div
                        v-if="selectedDates.length > 0 && selectedCompanyId !== null"
                        class="rounded-lg border border-blue-200 bg-blue-50/40 p-3"
                    >
                        <p class="eyebrow mb-1 text-blue-700">
                            Taxes induites par cette attribution
                        </p>

                        <div v-if="previewLoading" class="text-xs text-slate-500">
                            Calcul en cours…
                        </div>

                        <div v-else-if="preview" class="flex flex-col gap-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-600">
                                    Nouveaux jours pour ce couple
                                </span>
                                <span class="font-mono text-slate-900">
                                    +{{ preview.newDaysCount }} j
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Cumul futur</span>
                                <span class="font-mono text-slate-900">
                                    {{ preview.futureCumul }} j / {{ daysInYear(fiscalYear) }}
                                </span>
                            </div>
                            <div
                                v-if="preview.after.exemptionReasons.length > 0"
                                class="mt-1 flex flex-col gap-1 text-xs text-emerald-700"
                            >
                                <p
                                    v-for="(reason, i) in preview.after.exemptionReasons"
                                    :key="i"
                                    class="rounded-md bg-emerald-50 px-2 py-1"
                                >
                                    ✓ {{ reason }}
                                </p>
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t border-blue-200 pt-2"
                            >
                                <span class="text-slate-600">
                                    Taxe CO₂ ({{ preview.after.co2Method }})
                                </span>
                                <span class="font-mono text-slate-900">
                                    {{ formatEur(preview.after.co2Due) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Taxe polluants</span>
                                <span class="font-mono text-slate-900">
                                    {{ formatEur(preview.after.pollutantsDue) }}
                                </span>
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t border-blue-200 pt-2 text-base"
                            >
                                <span class="font-medium text-slate-900">
                                    Total annuel du couple
                                </span>
                                <span class="font-mono font-semibold text-slate-900">
                                    {{ formatEur(preview.after.totalDue) }}
                                </span>
                            </div>
                            <div
                                v-if="preview.incrementalDue > 0"
                                class="flex justify-between text-xs text-slate-500"
                            >
                                <span>dont induit par ces dates</span>
                                <span class="font-mono">
                                    +{{ formatEur(preview.incrementalDue) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <Button
                        type="button"
                        block
                        :loading="submitting"
                        :disabled="
                            selectedCompanyId === null ||
                            selectedDates.length === 0
                        "
                        @click="submit"
                    >
                        Créer {{ selectedDates.length }} attribution{{
                            selectedDates.length > 1 ? 's' : ''
                        }}
                    </Button>
                </section>
            </div>
        </aside>
    </transition>
</template>

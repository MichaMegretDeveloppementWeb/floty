<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- pattern Inertia useForm
   reçue en prop : la mutation directe est intentionnelle (le useForm
   est instancié dans le parent et passé tel quel pour éviter de
   pousser la logique submit dans ce partial purement présentationnel). */
import { computed, ref, toRef, watch } from 'vue';
import CompanyOptionTag from '@/Components/Domain/Company/CompanyOptionTag.vue';
import DriversMultiPicker from '@/Components/Domain/Driver/DriversMultiPicker.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useVehicleFullYearTax } from '@/Composables/Contract/useVehicleFullYearTax';
import {
    findLongestFreeSubrange,
    rangeConflicts,
} from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
import { indexById } from '@/Utils/Common/indexById';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type FormShape = {
    vehicle_id: number | null;
    company_id: number | null;
    driver_ids: number[];
    start_date: string;
    end_date: string;
    contract_reference: string | null;
    notes: string | null;
};

const props = defineProps<{
    form: FormShape & { errors: Record<string, string> };
    options: {
        vehicles: App.Data.User.Vehicle.VehicleFilterOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
    busyDatesByVehicleId: Record<number, string[]>;
}>();

// ── Sélecteur véhicule ──────────────────────────────────────────────
const vehicleOptions = computed(() => {
    const decorate = (v: App.Data.User.Vehicle.VehicleFilterOptionData): { value: number; label: string } => ({
        value: v.id,
        label: v.isExited && v.exitDate
            ? `${v.label} (retiré le ${formatDateFr(v.exitDate)})`
            : v.label,
    });

    const active = props.options.vehicles.filter((v) => !v.isExited).map(decorate);
    const exited = props.options.vehicles.filter((v) => v.isExited).map(decorate);

    return [...active, ...exited];
});

// ── Sélecteur entreprise (enrichi) ──────────────────────────────────
const companyOptions = computed(() =>
    props.options.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const companyById = computed(() => indexById(props.options.companies));

const vehicleIdModel = computed({
    get: (): number | null => props.form.vehicle_id,
    set: (v: string | number | null) => {
        props.form.vehicle_id = typeof v === 'number' ? v : null;
    },
});

// Taxe pleine annuelle du véhicule sélectionné, basée sur l'année de
// `start_date` saisie (fallback année courante quand la date n'est pas
// encore renseignée). Aide à la décision lors de la sélection · les
// règles fiscales évoluant d'une année à l'autre, on cale sur l'année
// de la location pour éviter d'afficher une valeur caduque.
//
// S2.5 (plan optim perf 2026-05-16) · calcul **on-demand** via
// endpoint AJAX `GET /app/vehicles/{vehicle}/full-year-tax`. Évite
// le pré-calcul lourd au mount (192 pipeline runs pour 64 vehicles
// × 3 années) · seul le véhicule effectivement sélectionné est
// calculé, et seulement à sa sélection. Le composable gère debounce
// 200 ms, fallback année voisine si année demandée pas en registry,
// et état `loading`.
const { result: vehicleFullYearTax, loading: vehicleFullYearTaxLoading } = useVehicleFullYearTax({
    vehicleId: toRef(props.form, 'vehicle_id'),
    startDate: toRef(props.form, 'start_date'),
});

const selectedVehicleFullYearTax = computed<{ year: number; tax: number; fallback: boolean } | null>(() => {
    const r = vehicleFullYearTax.value;

    if (r === null || r.cents === null) {
        return null;
    }

    return {
        year: r.year,
        tax: r.cents / 100,
        fallback: r.fallback,
    };
});

const companyIdModel = computed({
    get: (): number | null => props.form.company_id,
    set: (v: string | number | null) => {
        props.form.company_id = typeof v === 'number' ? v : null;
    },
});

// ── Plage de dates ──────────────────────────────────────────────────
const range = ref<{ startDate: string | null; endDate: string | null }>({
    startDate: props.form.start_date || null,
    endDate: props.form.end_date || null,
});
const ongoing = ref<boolean>(false);

watch(range, (value) => {
    props.form.start_date = value.startDate ?? '';
    props.form.end_date = value.endDate ?? '';
}, { deep: true });

const startDateModel = computed({
    get: () => props.form.start_date,
    set: (v: string) => {
        props.form.start_date = v;
        range.value = { ...range.value, startDate: v || null };
    },
});

const endDateModel = computed({
    get: () => props.form.end_date,
    set: (v: string) => {
        props.form.end_date = v;
        range.value = { ...range.value, endDate: v || null };
    },
});

const pickerYear = computed<number>(() => {
    if (props.form.start_date) {
        return Number(props.form.start_date.slice(0, 4));
    }

    return new Date().getFullYear();
});

const pickerStartMonth = computed<number>(() => {
    if (props.form.start_date) {
        return Number(props.form.start_date.slice(5, 7));
    }

    return new Date().getMonth() + 1;
});

const disabledDates = computed<string[]>(() => {
    if (props.form.vehicle_id === null) {
return [];
}

    return props.busyDatesByVehicleId[props.form.vehicle_id] ?? [];
});

// Quand on change de véhicule, on ré-ajuste la plage à la plus longue
// sous-plage libre trouvée. Aucune sous-plage libre → on efface.
watch(disabledDates, (newDisabled) => {
    if (range.value.startDate === null || range.value.endDate === null) {
return;
}

    const set = new Set(newDisabled);
    const conflicts = rangeConflicts(range.value.startDate, range.value.endDate, set);

    if (conflicts.length === 0) {
return;
}

    const sub = findLongestFreeSubrange(range.value.startDate, range.value.endDate, set);
    range.value = sub === null
        ? { startDate: null, endDate: null }
        : { startDate: sub.start, endDate: sub.end };
});

// ── Durée + type LCD/LLD live ───────────────────────────────────────
const durationDays = computed<number | null>(() => {
    const { startDate, endDate } = range.value;

    if (!startDate || !endDate) {
return null;
}

    const start = new Date(startDate);
    const end = new Date(endDate);
    const days = Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;

    return days > 0 ? days : null;
});

// Type dérivé localement (purement informatif · le backend recalcule
// via `Contract::deriveTypeFromDates` qui fait autorité). Règle
// simplifiée : ≤ 30 jours → LCD, sinon LLD.
const contractType = computed<'lcd' | 'lld' | null>(() => {
    if (durationDays.value === null) {
return null;
}

    return durationDays.value <= 30 ? 'lcd' : 'lld';
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <!-- ── ATTRIBUTION ──────────────────────────────────────── -->
        <section class="flex flex-col gap-4">
            <div>
                <p class="eyebrow">Attribution</p>
                <p class="mt-1 text-sm text-slate-500">
                    Quel véhicule, quelle entreprise.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <FieldLabel for="vehicle_id">Véhicule</FieldLabel>
                    <SearchableSelect
                        id="vehicle_id"
                        v-model="vehicleIdModel"
                        placeholder="Choisir un véhicule…"
                        :options="vehicleOptions"
                    />
                    <p
                        v-if="form.vehicle_id !== null && vehicleFullYearTaxLoading"
                        class="mt-1.5 font-mono text-[11px] text-slate-400 tabular-nums"
                    >
                        Taxe pleine <span class="inline-block animate-pulse">…</span>
                    </p>
                    <p
                        v-else-if="selectedVehicleFullYearTax"
                        class="mt-1.5 font-mono text-[11px] text-slate-500 tabular-nums"
                    >
                        Taxe pleine
                        <span class="text-slate-700">{{ formatEur(selectedVehicleFullYearTax.tax, 0) }}</span>
                        <span v-if="selectedVehicleFullYearTax.fallback" class="text-slate-400">
                            (dernière année connue · {{ selectedVehicleFullYearTax.year }})
                        </span>
                        <span v-else>({{ selectedVehicleFullYearTax.year }})</span>
                    </p>
                    <InputError :message="form.errors.vehicle_id" />
                </div>
                <div>
                    <FieldLabel for="company_id">Entreprise utilisatrice</FieldLabel>
                    <SearchableSelect
                        id="company_id"
                        v-model="companyIdModel"
                        placeholder="Choisir une entreprise…"
                        :options="companyOptions"
                    >
                        <template #option="{ option }">
                            <CompanyOptionTag
                                v-if="companyById.get(Number(option.value))"
                                :company="companyById.get(Number(option.value))!"
                            />
                            <template v-else>{{ option.label }}</template>
                        </template>
                        <template #selected="{ option }">
                            <CompanyOptionTag
                                v-if="companyById.get(Number(option.value))"
                                :company="companyById.get(Number(option.value))!"
                            />
                            <template v-else>{{ option.label }}</template>
                        </template>
                    </SearchableSelect>
                    <InputError :message="form.errors.company_id" />
                </div>
            </div>
        </section>

        <hr class="border-slate-100" />

        <!-- ── PÉRIODE ──────────────────────────────────────────── -->
        <section class="flex flex-col gap-4">
            <p class="eyebrow">Période</p>

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[140px] flex-1">
                    <FieldLabel for="start_date">Du</FieldLabel>
                    <DateInput id="start_date" v-model="startDateModel" />
                </div>
                <div class="min-w-[140px] flex-1">
                    <FieldLabel for="end_date">Au</FieldLabel>
                    <DateInput id="end_date" v-model="endDateModel" />
                </div>
                <div
                    v-if="durationDays !== null"
                    class="flex items-center gap-2 pb-2 text-sm"
                >
                    <span class="font-mono text-slate-700">{{ durationDays }} j</span>
                    <span class="text-slate-300">·</span>
                    <span
                        class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold tracking-wide text-slate-700 uppercase"
                    >
                        {{ contractType === 'lcd' ? 'LCD' : 'LLD' }}
                    </span>
                </div>
            </div>

            <DateRangePicker
                v-model:range="range"
                v-model:ongoing="ongoing"
                :year="pickerYear"
                :start-month="pickerStartMonth"
                :disabled-dates="disabledDates"
            />

            <p
                v-if="form.vehicle_id === null"
                class="text-xs text-slate-500"
            >
                Sélectionnez un véhicule pour voir les jours déjà occupés
                par d'autres locations actives.
            </p>
            <p
                v-else-if="disabledDates.length > 0"
                class="text-xs text-slate-500"
            >
                Les jours déjà occupés par une autre location de ce véhicule
                (barrés) ne peuvent pas être inclus dans la plage.
            </p>
            <InputError :message="form.errors.start_date || form.errors.end_date" />
        </section>

        <hr class="border-slate-100" />

        <!-- ── CONDUCTEURS ──────────────────────────────────────── -->
        <section class="flex flex-col gap-4">
            <p class="eyebrow">Conducteurs</p>

            <DriversMultiPicker
                :model-value="form.driver_ids"
                :company-id="form.company_id"
                :start-date="form.start_date || null"
                :end-date="form.end_date || null"
                @update:model-value="(v) => (form.driver_ids = v)"
            />
            <InputError :message="form.errors.driver_ids" />
        </section>

        <hr class="border-slate-100" />

        <!-- ── DÉTAILS ──────────────────────────────────────────── -->
        <section class="flex flex-col gap-4">
            <p class="eyebrow">Détails</p>

            <div>
                <FieldLabel for="contract_reference">Référence location</FieldLabel>
                <TextInput
                    id="contract_reference"
                    :model-value="form.contract_reference ?? ''"
                    placeholder="Ex. : CTR-2024-001"
                    @update:model-value="(v) => (form.contract_reference = v === '' ? null : v)"
                />
                <InputError :message="form.errors.contract_reference" />
            </div>

            <div>
                <FieldLabel for="notes">Notes</FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    placeholder="Conditions particulières, contact, etc."
                />
                <InputError :message="form.errors.notes" />
            </div>
        </section>
    </div>
</template>

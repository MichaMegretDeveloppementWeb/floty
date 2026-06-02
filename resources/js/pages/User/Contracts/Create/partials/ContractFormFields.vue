<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- Inertia useForm received as
   a prop. Direct mutation is intentional: the useForm lives in the
   parent and is passed through to keep this partial presentational. */
import { computed, ref, toRef, watch } from 'vue';
import CompanyOptionTag from '@/Components/Domain/Company/CompanyOptionTag.vue';
import DriversMultiPicker from '@/Components/Domain/Driver/DriversMultiPicker.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useVehicleFullYearTax } from '@/Composables/Contract/useVehicleFullYearTax';
import { useVehicleYearlyRates } from '@/Composables/Contract/useVehicleYearlyRates';
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

// Full-year tax for the selected vehicle, anchored on the start_date
// year (fallback to the current year). Fetched on-demand via AJAX to
// keep the mount cheap.
const { result: vehicleFullYearTax, loading: vehicleFullYearTaxLoading } = useVehicleFullYearTax({
    vehicleId: toRef(props.form, 'vehicle_id'),
    startDate: toRef(props.form, 'start_date'),
});

const { result: vehicleYearlyRates, loading: vehicleYearlyRatesLoading } = useVehicleYearlyRates({
    vehicleId: toRef(props.form, 'vehicle_id'),
    startDate: toRef(props.form, 'start_date'),
});

const hasYearlyRates = computed<boolean>(
    () =>
        vehicleYearlyRates.value !== null
        && (vehicleYearlyRates.value.dailyCents !== null
            || vehicleYearlyRates.value.weeklyCents !== null
            || vehicleYearlyRates.value.monthlyCents !== null),
);

const formatRate = (cents: number | null): string =>
    cents === null ? '-' : formatEur(cents / 100, 0);

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

const range = ref<{ startDate: string | null; endDate: string | null }>({
    startDate: props.form.start_date || null,
    endDate: props.form.end_date || null,
});
const ongoing = ref<boolean>(false);

watch(range, (value) => {
    props.form.start_date = value.startDate ?? '';
    props.form.end_date = value.endDate ?? '';
}, { deep: true });

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

// When the vehicle changes, snap the range to the longest free
// subrange. If none exists, clear it.
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

// Local derivation only (informative). The backend recalculates the
// type via `Contract::deriveTypeFromDates`, which is authoritative.
const contractType = computed<'lcd' | 'lld' | null>(() => {
    if (durationDays.value === null) {
return null;
}

    return durationDays.value <= 30 ? 'lcd' : 'lld';
});
</script>

<template>
    <div class="flex flex-col gap-8">
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
                    <p
                        v-if="form.vehicle_id !== null && vehicleYearlyRatesLoading"
                        class="mt-1 font-mono text-[11px] text-slate-400 tabular-nums"
                    >
                        Loyer <span class="inline-block animate-pulse">…</span>
                    </p>
                    <p
                        v-else-if="vehicleYearlyRates && hasYearlyRates"
                        class="mt-1 font-mono text-[11px] text-slate-500 tabular-nums"
                    >
                        Loyer
                        <span class="text-slate-400">J</span>
                        <span class="text-slate-700">{{ formatRate(vehicleYearlyRates.dailyCents) }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                        <span class="text-slate-400">S</span>
                        <span class="text-slate-700">{{ formatRate(vehicleYearlyRates.weeklyCents) }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                        <span class="text-slate-400">M</span>
                        <span class="text-slate-700">{{ formatRate(vehicleYearlyRates.monthlyCents) }}</span>
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

        <section class="flex flex-col gap-4">
            <p class="eyebrow">Période</p>

            <DateRangePicker
                v-model:range="range"
                v-model:ongoing="ongoing"
                :year="pickerYear"
                :start-month="pickerStartMonth"
                :disabled-dates="disabledDates"
            />

            <div
                v-if="durationDays !== null"
                class="flex items-center gap-2 text-sm"
            >
                <span class="font-mono text-slate-700">{{ durationDays }} j</span>
                <span class="text-slate-300">·</span>
                <span
                    class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold tracking-wide text-slate-700 uppercase"
                >
                    {{ contractType === 'lcd' ? 'LCD' : 'LLD' }}
                </span>
            </div>

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

<script setup lang="ts">
/**
 * Form fields for RentalDiscount Create/Edit.
 *
 * Presentational only: bindings and render. The parent composable
 * `useRentalDiscountForm` owns the state, percent <-> basis-points
 * conversion, and the overlap check. The conflict card only appears
 * when overlaps are present (the submit stays silently disabled).
 */
import type { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import VehiclesMultiPicker from '@/Components/Domain/RentalDiscount/VehiclesMultiPicker.vue';
import type { VehicleOption } from '@/Components/Domain/RentalDiscount/VehiclesMultiPicker.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type {
    ConflictItem,
    RentalDiscountFormPayload,
} from '@/Composables/RentalDiscount/Form/useRentalDiscountForm';
import type { DateRange } from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatPercentFromBasisPoints } from '@/Utils/format/formatPercent';

type CompanyOption = {
    id: number;
    shortCode: string;
    legalName: string;
    color: string;
};

const props = defineProps<{
    /** Inertia useForm shared with the parent (reactive state + errors). */
    form: ReturnType<typeof useForm<RentalDiscountFormPayload>>;
    companies: readonly CompanyOption[];
    vehicles: readonly VehicleOption[];
    /** Edit mode: companyId becomes read-only (cannot be changed on an existing discount). */
    isEdit: boolean;
    discountPercent: number;
    appliesToAllVehicles: boolean;
    range: DateRange;
    ongoing: boolean;
    pickerInitialYear: number;
    pickerInitialMonth: number;
    conflicts: readonly ConflictItem[];
}>();

const emit = defineEmits<{
    'update:discount-percent': [value: number];
    'update:applies-to-all-vehicles': [value: boolean];
    'update:range': [value: DateRange];
    'update:ongoing': [value: boolean];
    // The Inertia `form` is owned by the parent page; this component never
    // mutates it directly (props are read-only). Field edits are emitted and
    // the parent applies them to its own `form`.
    'update:company-id': [value: number | null];
    'update:label': [value: string];
    'update:notes': [value: string];
    'update:vehicle-ids': [value: number[]];
}>();

const discountPercentModel = computed<number>({
    get: () => props.discountPercent,
    set: (value: number) => emit('update:discount-percent', value),
});

const appliesToAllVehiclesModel = computed<boolean>({
    get: () => props.appliesToAllVehicles,
    set: (value: boolean) => emit('update:applies-to-all-vehicles', value),
});

const rangeModel = computed<DateRange>({
    get: () => props.range,
    set: (value: DateRange) => emit('update:range', value),
});

const ongoingModel = computed<boolean>({
    get: () => props.ongoing,
    set: (value: boolean) => emit('update:ongoing', value),
});

// Company selector (Create only): read the prop, emit the change to the parent.
const companyIdModel = computed<number | null>({
    get: () => props.form.company_id,
    set: (value: number | null) => emit('update:company-id', value),
});

// TextInput's v-model is `string`; the form field is `string | null` (optional
// libellé). Bridge the two without changing the payload semantics (empty stays '').
const labelModel = computed<string>({
    get: () => props.form.label ?? '',
    set: (value: string) => emit('update:label', value),
});

// Notes textarea: read the prop (null → ''), emit edits to the parent.
const notesModel = computed<string>({
    get: () => props.form.notes ?? '',
    set: (value: string) => emit('update:notes', value),
});

const vehicleIdsModel = computed<number[]>({
    get: () => props.form.vehicle_ids,
    set: (value: number[]) => emit('update:vehicle-ids', value),
});

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const lockedCompanyLabel = computed<string>(() => {
    const c = props.companies.find((c) => c.id === props.form.company_id);

    return c ? `${c.shortCode} · ${c.legalName}` : '';
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Entreprise bénéficiaire
                </h2>
            </template>
            <div class="flex flex-col gap-2">
                <FieldLabel for="field-company" required>
                    Entreprise
                </FieldLabel>
                <SearchableSelect
                    v-if="!isEdit"
                    id="field-company"
                    v-model="companyIdModel"
                    :options="companyOptions"
                    placeholder="Sélectionner une entreprise"
                />
                <p v-else class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ lockedCompanyLabel }}
                </p>
                <p v-if="isEdit" class="text-xs text-slate-500">
                    L'entreprise d'une réduction existante ne peut pas être modifiée. Créez une nouvelle réduction si nécessaire.
                </p>
                <InputError :message="form.errors.company_id" />
            </div>
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Période et taux
                </h2>
            </template>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_auto]">
                <div class="flex flex-col gap-2">
                    <FieldLabel for="field-date-range" required>
                        Période d'application
                    </FieldLabel>
                    <DateRangePicker
                        id="field-date-range"
                        v-model:range="rangeModel"
                        v-model:ongoing="ongoingModel"
                        :year="pickerInitialYear"
                        :start-month="pickerInitialMonth"
                    />
                    <InputError :message="form.errors.start_date" />
                    <InputError :message="form.errors.end_date" />
                </div>
                <div class="flex flex-col gap-2 lg:w-[14em]">
                    <FieldLabel for="field-percent" required>
                        Pourcentage de réduction
                    </FieldLabel>
                    <div class="relative">
                        <NumberInput
                            id="field-percent"
                            v-model="discountPercentModel"
                            :min="0.5"
                            :max="100"
                            :step="0.5"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-500">
                            %
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        De 0,5 % à 100 %, par pas de 0,5.
                    </p>
                    <InputError :message="form.errors.discount_basis_points" />
                </div>
            </div>
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Véhicules concernés
                </h2>
            </template>
            <div class="flex flex-col gap-4">
                <CheckboxInput
                    id="field-all-vehicles"
                    v-model="appliesToAllVehiclesModel"
                    label="Appliquer à tous les véhicules de l'entreprise"
                />
                <p class="text-xs text-slate-500">
                    <template v-if="appliesToAllVehicles">
                        La réduction s'appliquera à l'ensemble des véhicules utilisés par l'entreprise sur la période, incluant les véhicules ajoutés ultérieurement.
                    </template>
                    <template v-else>
                        Sélectionnez précisément les véhicules concernés ci-dessous.
                    </template>
                </p>
                <VehiclesMultiPicker
                    v-model="vehicleIdsModel"
                    :vehicles="vehicles"
                    :disabled="appliesToAllVehicles"
                />
                <InputError :message="form.errors.vehicle_ids" />
            </div>
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Identification
                </h2>
            </template>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <FieldLabel for="field-label">
                        Libellé
                    </FieldLabel>
                    <TextInput
                        id="field-label"
                        v-model="labelModel"
                        placeholder="Pack fidélité 2026, Engagement annuel..."
                        :maxlength="120"
                    />
                    <p class="text-xs text-slate-500">
                        Court nom mémorisable affiché sur la facture et les listings. Optionnel.
                    </p>
                    <InputError :message="form.errors.label" />
                </div>
                <div class="flex flex-col gap-2">
                    <FieldLabel for="field-notes">
                        Notes internes
                    </FieldLabel>
                    <textarea
                        id="field-notes"
                        v-model="notesModel"
                        rows="4"
                        maxlength="5000"
                        placeholder="Contexte, conditions négociées, référence contrat externe..."
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p class="text-xs text-slate-500">
                        Visibles uniquement par les gestionnaires de flotte. Non imprimées sur la facture.
                    </p>
                    <InputError :message="form.errors.notes" />
                </div>
            </div>
        </Card>

        <div
            v-if="conflicts.length > 0"
            class="rounded-xl border border-rose-200 bg-rose-50/50 p-4"
        >
            <p class="text-sm font-semibold text-rose-900">
                Chevauchement détecté avec
                {{ conflicts.length }} réduction{{ conflicts.length > 1 ? 's' : '' }}
                existante{{ conflicts.length > 1 ? 's' : '' }}
            </p>
            <ul class="mt-2 flex flex-col gap-1.5 text-xs text-rose-800">
                <li
                    v-for="conflict in conflicts"
                    :key="conflict.id"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <span class="font-mono font-medium">
                        {{ formatPercentFromBasisPoints(conflict.discountBasisPoints) }}
                    </span>
                    <span>
                        « {{ conflict.label ?? `Réduction #${conflict.id}` }} »
                    </span>
                    <span>
                        du {{ formatDateFr(conflict.startDate) }}
                        au {{ formatDateFr(conflict.endDate) }}
                    </span>
                    <span class="text-rose-600">
                        ·
                        <template v-if="conflict.isAllVehicles">
                            tous véhicules
                        </template>
                        <template v-else>
                            {{ conflict.vehiclesCount }} véhicule{{ conflict.vehiclesCount > 1 ? 's' : '' }}
                        </template>
                    </span>
                </li>
            </ul>
            <p class="mt-3 text-xs text-rose-800">
                Ajustez la période, le périmètre véhicules ou modifiez la réduction conflictuelle pour pouvoir enregistrer.
            </p>
        </div>
    </div>
</template>

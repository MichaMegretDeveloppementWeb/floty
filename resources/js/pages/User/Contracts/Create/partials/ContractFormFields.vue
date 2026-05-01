<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- pattern Inertia useForm
   reçue en prop : la mutation directe est intentionnelle (le useForm
   est instancié dans le parent et passé tel quel pour éviter de
   pousser la logique submit dans ce partial purement présentationnel). */
import { computed, ref, watch } from 'vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type FormShape = {
    vehicle_id: number | null;
    company_id: number | null;
    driver_id: number | null;
    start_date: string;
    end_date: string;
    contract_reference: string | null;
    notes: string | null;
};

const props = defineProps<{
    form: FormShape & { errors: Record<string, string> };
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
    /**
     * Map véhicule → dates ISO déjà occupées par un autre contrat actif.
     * Empêche l'utilisateur de saisir une plage en chevauchement (chantier
     * H — l'erreur backend reste le filet de sécurité). Le picker grise
     * les dates concernées dès qu'un véhicule est sélectionné.
     */
    busyDatesByVehicleId: Record<number, string[]>;
}>();

// Tri Actifs en premier, puis Retirés ; les Retirés sont annotés
// dans leur label pour rester identifiables dans le SearchableSelect
// (qui ne gère pas natively les optgroups). Utile pour permettre
// l'édition d'un contrat antérieur sur un véhicule sorti de flotte.
const vehicleOptions = computed(() => {
    const decorate = (v: App.Data.User.Vehicle.VehicleOptionData): { value: number; label: string } => ({
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

// Wrappers v-model : SearchableSelect émet `string | number | null` ;
// on borne à `number | null` côté formulaire pour cohérence avec
// VehicleOptionData.id / CompanyOptionData.id (typés number).
const vehicleIdModel = computed({
    get: (): number | null => props.form.vehicle_id,
    set: (v: string | number | null) => {
        props.form.vehicle_id = typeof v === 'number' ? v : null;
    },
});

const companyIdModel = computed({
    get: (): number | null => props.form.company_id,
    set: (v: string | number | null) => {
        props.form.company_id = typeof v === 'number' ? v : null;
    },
});

// DateRangePicker pilote `range = { startDate, endDate }` ; on synchronise
// avec form.start_date / form.end_date à chaque mutation.
const range = ref<{ startDate: string | null; endDate: string | null }>({
    startDate: props.form.start_date || null,
    endDate: props.form.end_date || null,
});
const ongoing = ref<boolean>(false);

watch(range, (value) => {
    props.form.start_date = value.startDate ?? '';
    props.form.end_date = value.endDate ?? '';
}, { deep: true });

const { currentYear: fiscalYear } = useFiscalYear();

const pickerYear = computed<number>(() => {
    if (props.form.start_date) {
        return Number(props.form.start_date.slice(0, 4));
    }

    return fiscalYear.value;
});

const disabledDates = computed<string[]>(() => {
    if (props.form.vehicle_id === null) {
        return [];
    }

    return props.busyDatesByVehicleId[props.form.vehicle_id] ?? [];
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <FieldLabel for="vehicle_id">Véhicule</FieldLabel>
                <SearchableSelect
                    id="vehicle_id"
                    v-model="vehicleIdModel"
                    placeholder="Choisir un véhicule…"
                    :options="vehicleOptions"
                />
                <InputError :message="form.errors.vehicle_id" />
            </div>
            <div>
                <FieldLabel for="company_id">Entreprise utilisatrice</FieldLabel>
                <SearchableSelect
                    id="company_id"
                    v-model="companyIdModel"
                    placeholder="Choisir une entreprise…"
                    :options="companyOptions"
                />
                <InputError :message="form.errors.company_id" />
            </div>
        </div>

        <div class="my-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-600">
                Plage du contrat
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Le type LCD/LLD est déterminé automatiquement selon la durée
                (≤ 30 jours ou mois civil entier → LCD ; sinon LLD).
            </p>
            <div class="mt-2">
                <DateRangePicker
                    v-model:range="range"
                    v-model:ongoing="ongoing"
                    :year="pickerYear"
                    :start-month="form.start_date ? Number(form.start_date.slice(5, 7)) : 1"
                    :disabled-dates="disabledDates"
                />
            </div>
            <p
                v-if="form.vehicle_id === null"
                class="mt-1 text-xs text-slate-500"
            >
                Sélectionnez un véhicule pour voir les jours déjà occupés
                par d'autres contrats actifs.
            </p>
            <p
                v-else-if="disabledDates.length > 0"
                class="mt-1 text-xs text-slate-500"
            >
                Les jours déjà occupés par un autre contrat de ce véhicule
                (barrés) ne peuvent pas être inclus dans la plage.
            </p>
            <InputError :message="form.errors.start_date || form.errors.end_date" />
        </div>

        <div>
            <FieldLabel for="contract_reference">
                Référence contrat (optionnel)
            </FieldLabel>
            <TextInput
                id="contract_reference"
                :model-value="form.contract_reference ?? ''"
                placeholder="Ex. : CTR-2024-001"
                @update:model-value="(v) => (form.contract_reference = v === '' ? null : v)"
            />
            <InputError :message="form.errors.contract_reference" />
        </div>

        <div>
            <FieldLabel for="notes">Notes (optionnel)</FieldLabel>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                placeholder="Conditions particulières, contact, etc."
            />
            <InputError :message="form.errors.notes" />
        </div>
    </div>
</template>

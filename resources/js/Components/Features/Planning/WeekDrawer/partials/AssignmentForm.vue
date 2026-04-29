<script setup lang="ts">
import { computed, watch } from 'vue';
import MultiDatePicker from '@/Components/Features/Planning/MultiDatePicker.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';
import { useApi } from '@/Composables/Shared/useApi';
import { storeBulk as storeBulkRoute } from '@/routes/user/planning/contracts';
import FiscalPreviewCard from './FiscalPreviewCard.vue';

type Company = App.Data.User.Company.CompanyOptionData;

const props = defineProps<{
    vehicleId: number;
    companies: Company[];
    fiscalYear: number;
    startMonth: number;
    weekDates: string[];
    disabledDates: string[];
    selectedCompanyId: number | null;
    selectedDates: string[];
}>();

const emit = defineEmits<{
    'update:selectedCompanyId': [value: number | null];
    'update:selectedDates': [value: string[]];
    submitted: [];
}>();

const api = useApi();
const { preview, loading: previewLoading, fetch: fetchPreview, reset: resetPreview } = useFiscalPreview();

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: String(c.id),
        label: `${c.shortCode} — ${c.legalName}`,
    })),
);

const companyIdString = computed({
    get: () =>
        props.selectedCompanyId !== null ? String(props.selectedCompanyId) : '',
    set: (v: string) => {
        emit(
            'update:selectedCompanyId',
            v === '' ? null : Number(v),
        );
    },
});

const datesProxy = computed({
    get: () => props.selectedDates,
    set: (v: string[]) => emit('update:selectedDates', v),
});

// Dates de cette semaine attribuées au couple courant — pour info dans
// le picker. Pour une vision annuelle complète il faudrait charger
// l'historique du couple via l'API.
const currentPairDatesHint = computed((): string[] => {
    if (props.selectedCompanyId === null) {
        return [];
    }

    return [];
});

const submitting = computed(() => false);
const canSubmit = computed(
    () => props.selectedCompanyId !== null && props.selectedDates.length > 0,
);

// Re-déclenche le preview à chaque changement de couple ou dates.
watch(
    () => [props.selectedCompanyId, props.selectedDates] as const,
    ([companyId, dates]) => {
        fetchPreview({
            vehicleId: props.vehicleId,
            companyId,
            dates,
        });
    },
    { deep: true },
);

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    try {
        const sorted = [...props.selectedDates].sort();
        // canSubmit garantit selectedDates.length > 0
        const startDate = sorted[0] as string;
        const endDate = sorted[sorted.length - 1] as string;

        const payload: App.Data.User.Contract.BulkStoreContractsData = {
            vehicleIds: [props.vehicleId],
            companyId: props.selectedCompanyId as number,
            driverId: null,
            startDate,
            endDate,
            contractReference: null,
            contractType: 'lcd',
            notes: null,
        };

        await api.post<{ createdIds: number[] }>(storeBulkRoute.url(), payload);
        resetPreview();
        emit('submitted');
    } catch {
        // Toast erreur déjà affiché par useApi
    }
}
</script>

<template>
    <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
        <p class="eyebrow mb-0">Attribuer des jours</p>

        <SelectInput
            v-model="companyIdString"
            label="Entreprise"
            placeholder="Choisir une entreprise…"
            :options="companyOptions"
        />

        <MultiDatePicker
            v-model:selected="datesProxy"
            :year="fiscalYear"
            :start-month="startMonth"
            :disabled-dates="disabledDates"
            :current-pair-dates="currentPairDatesHint"
            :highlight-dates="weekDates"
        />

        <FiscalPreviewCard
            v-if="selectedDates.length > 0 && selectedCompanyId !== null"
            :preview="preview"
            :loading="previewLoading"
        />

        <Button
            type="button"
            block
            :loading="submitting"
            :disabled="!canSubmit"
            @click="submit"
        >
            Créer {{ selectedDates.length }} attribution{{
                selectedDates.length > 1 ? 's' : ''
            }}
        </Button>
    </section>
</template>

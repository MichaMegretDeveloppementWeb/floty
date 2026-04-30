<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';
import { useApi } from '@/Composables/Shared/useApi';
import { storeBulk as storeBulkRoute } from '@/routes/user/planning/contracts';
import FiscalPreviewCard from './FiscalPreviewCard.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type DateRange = { startDate: string | null; endDate: string | null };

const props = defineProps<{
    vehicleId: number;
    companies: Company[];
    fiscalYear: number;
    startMonth: number;
    weekDates: string[];
    disabledDates: string[];
    selectedCompanyId: number | null;
    selectedRange: DateRange;
}>();

const emit = defineEmits<{
    'update:selectedCompanyId': [value: number | null];
    'update:selectedRange': [value: DateRange];
    submitted: [];
}>();

const api = useApi();
const { preview, loading: previewLoading, fetch: fetchPreview, reset: resetPreview } = useFiscalPreview();

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} — ${c.legalName}`,
    })),
);

const companyIdModel = computed({
    get: (): number | null => props.selectedCompanyId,
    set: (v: string | number | null) => {
        emit('update:selectedCompanyId', typeof v === 'number' ? v : null);
    },
});

const rangeProxy = computed<DateRange>({
    get: () => props.selectedRange,
    set: (v: DateRange) => emit('update:selectedRange', v),
});
const ongoing = ref<boolean>(false);

const submitting = ref<boolean>(false);

const hasRange = computed(
    () =>
        props.selectedRange.startDate !== null &&
        props.selectedRange.endDate !== null,
);

const canSubmit = computed(
    () =>
        props.selectedCompanyId !== null &&
        hasRange.value &&
        !submitting.value,
);

// Helper : expand la plage en liste de dates ISO (pour la preview qui
// attend `dates: string[]` côté API actuelle).
const datesInRange = computed<string[]>(() => {
    const start = props.selectedRange.startDate;
    const end = props.selectedRange.endDate;

    if (start === null || end === null) {
        return [];
    }

    const out: string[] = [];
    const cursor = new Date(start);
    const stop = new Date(end);

    while (cursor <= stop) {
        out.push(cursor.toISOString().slice(0, 10));
        cursor.setDate(cursor.getDate() + 1);
    }

    return out;
});

// Re-déclenche la preview à chaque changement de couple ou de plage.
watch(
    () => [props.selectedCompanyId, datesInRange.value] as const,
    ([companyId, dates]) => {
        if (dates.length === 0 || companyId === null) {
            return;
        }

        fetchPreview({ vehicleId: props.vehicleId, companyId, dates });
    },
    { deep: true },
);

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;

    try {
        const payload: App.Data.User.Contract.BulkStoreContractsData = {
            vehicleIds: [props.vehicleId],
            companyId: props.selectedCompanyId as number,
            driverId: null,
            startDate: props.selectedRange.startDate as string,
            endDate: props.selectedRange.endDate as string,
            contractReference: null,
            notes: null,
        };

        await api.post<{ createdIds: number[] }>(storeBulkRoute.url(), payload);
        resetPreview();
        emit('submitted');
    } catch {
        // Toast erreur déjà affiché par useApi
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="flex flex-col gap-3 border-t border-slate-100 pt-4">
        <p class="eyebrow mb-0">Créer un contrat</p>

        <SearchableSelect
            v-model="companyIdModel"
            label="Entreprise"
            placeholder="Choisir une entreprise…"
            :options="companyOptions"
        />

        <DateRangePicker
            v-model:range="rangeProxy"
            v-model:ongoing="ongoing"
            :year="fiscalYear"
            :start-month="startMonth"
            :disabled-dates="disabledDates"
            :highlight-dates="weekDates"
        />

        <FiscalPreviewCard
            v-if="hasRange && selectedCompanyId !== null"
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
            Créer le contrat
        </Button>
    </section>
</template>

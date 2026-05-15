<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import CompanyOptionTag from '@/Components/Domain/Company/CompanyOptionTag.vue';
import ContractDocumentsModal from '@/Components/Domain/Contract/ContractDocumentsModal.vue';
import DriversMultiPicker from '@/Components/Domain/Driver/DriversMultiPicker.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { useContractDocuments } from '@/Composables/Contract/useContractDocuments';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';
import { useApi } from '@/Composables/Shared/useApi';
import { storeBulk as storeBulkRoute } from '@/routes/user/planning/contracts';
import { indexById } from '@/Utils/Common/indexById';
import FiscalPreviewCard from './FiscalPreviewCard.vue';
import MoreOptionsSection from './MoreOptionsSection.vue';

type Company = App.Data.User.Company.CompanyOptionData;
type DateRange = { startDate: string | null; endDate: string | null };

const props = withDefaults(
    defineProps<{
        vehicleId: number;
        companies: Company[];
        fiscalYear: number;
        startMonth: number;
        weekDates: string[];
        disabledDates: string[];
        selectedCompanyId: number | null;
        selectedRange: DateRange;
        /**
         * Mode company-locked (chantier P3) : quand fourni, le sélecteur
         * entreprise est masqué et l'entreprise est hardcodée. Le payload
         * submit utilise `lockedCompany.id`.
         */
        lockedCompany?: Company | null;
    }>(),
    {
        lockedCompany: null,
    },
);

const emit = defineEmits<{
    'update:selectedCompanyId': [value: number | null];
    'update:selectedRange': [value: DateRange];
    submitted: [];
}>();

const api = useApi();
const { uploadMany } = useContractDocuments();
const { preview, loading: previewLoading, fetch: fetchPreview, reset: resetPreview } = useFiscalPreview();

// ── Sélecteur entreprise enrichi ────────────────────────────────────
const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const companyById = computed(() => indexById(props.companies));

const companyIdModel = computed({
    get: (): number | null => props.selectedCompanyId,
    set: (v: string | number | null) => {
        emit('update:selectedCompanyId', typeof v === 'number' ? v : null);
    },
});

// ── Plage ───────────────────────────────────────────────────────────
const rangeProxy = computed<DateRange>({
    get: () => props.selectedRange,
    set: (v: DateRange) => emit('update:selectedRange', v),
});
const ongoing = ref<boolean>(false);

// ── State local ─────────────────────────────────────────────────────
const submitting = ref<boolean>(false);
const selectedDriverIds = ref<number[]>([]);
const contractReference = ref<string | null>(null);
const notes = ref<string | null>(null);
const pendingFiles = ref<File[]>([]);
const documentsModalOpen = ref<boolean>(false);

watch(
    () => props.selectedCompanyId,
    () => {
        selectedDriverIds.value = [];
        contractReference.value = null;
        notes.value = null;
        pendingFiles.value = [];
    },
);

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

// Durée live affichée à côté du titre PÉRIODE.
const durationDays = computed<number | null>(() => {
    const start = props.selectedRange.startDate;
    const end = props.selectedRange.endDate;

    if (start === null || end === null) {
return null;
}

    const days = Math.floor(
        (new Date(end).getTime() - new Date(start).getTime()) / (1000 * 60 * 60 * 24),
    ) + 1;

    return days > 0 ? days : null;
});

const contractType = computed<'lcd' | 'lld' | null>(() => {
    if (durationDays.value === null) {
return null;
}

    return durationDays.value <= 30 ? 'lcd' : 'lld';
});

// ── Preview fiscale ─────────────────────────────────────────────────
// Le backend prend min/max des dates pour reconstruire la plage du
// contrat synthétique · pas besoin d'expandre tous les jours côté
// front (et ça évite les bugs de timezone sur les transitions DST).
const datesInRange = computed<string[]>(() => {
    const start = props.selectedRange.startDate;
    const end = props.selectedRange.endDate;

    if (start === null || end === null) {
        return [];
    }

    return start === end ? [start] : [start, end];
});

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

// ── Submit ──────────────────────────────────────────────────────────
async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;

    try {
        const payload: Record<string, unknown> = {
            vehicle_ids: [props.vehicleId],
            // En mode company-locked, l'id vient de lockedCompany
            // (sélecteur masqué). Sinon, on lit selectedCompanyId.
            company_id: (props.lockedCompany?.id ?? props.selectedCompanyId) as number,
            driver_ids: selectedDriverIds.value,
            start_date: props.selectedRange.startDate as string,
            end_date: props.selectedRange.endDate as string,
            contract_reference: contractReference.value,
            notes: notes.value,
        };

        const response = await api.post<{ createdIds: number[] }>(
            storeBulkRoute.url(),
            payload,
        );

        if (pendingFiles.value.length > 0 && response.createdIds.length > 0) {
            const contractId = response.createdIds[0]!;
            await uploadMany(contractId, pendingFiles.value);
        }

        resetPreview();
        pendingFiles.value = [];
        contractReference.value = null;
        notes.value = null;
        emit('submitted');
    } catch {
        // Toast erreur déjà affiché par useApi
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="flex flex-col gap-5 border-t border-slate-100 pt-5">
        <!-- ── Entreprise ─────────────────────────────────────────── -->
        <div class="flex flex-col gap-2">
            <p class="eyebrow">Entreprise</p>
            <!-- Mode company-locked (chantier P3) : entreprise verrouillée
                 par la Vue Entreprise courante, pas de sélecteur. -->
            <div
                v-if="lockedCompany"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
            >
                <CompanyOptionTag :company="lockedCompany" />
            </div>
            <SearchableSelect
                v-else
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
        </div>

        <hr class="border-slate-100" />

        <!-- ── Période ────────────────────────────────────────────── -->
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between gap-2">
                <p class="eyebrow">Période</p>
                <span
                    v-if="durationDays !== null"
                    class="inline-flex items-center gap-1.5 text-xs"
                >
                    <span class="font-mono text-slate-700">{{ durationDays }} j</span>
                    <span class="text-slate-300">·</span>
                    <span
                        class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-slate-700 uppercase"
                    >
                        {{ contractType === 'lcd' ? 'LCD' : 'LLD' }}
                    </span>
                </span>
            </div>
            <DateRangePicker
                v-model:range="rangeProxy"
                v-model:ongoing="ongoing"
                :year="fiscalYear"
                :start-month="startMonth"
                :disabled-dates="disabledDates"
                :highlight-dates="weekDates"
            />
        </div>

        <hr class="border-slate-100" />

        <!-- ── Conducteurs ────────────────────────────────────────── -->
        <div class="flex flex-col gap-2">
            <p class="eyebrow">
                Conducteurs
                <span class="ml-1 font-normal normal-case tracking-normal text-slate-400">
                    · optionnel
                </span>
            </p>
            <DriversMultiPicker
                v-model="selectedDriverIds"
                :company-id="selectedCompanyId"
                :start-date="selectedRange.startDate"
                :end-date="selectedRange.endDate"
            />
        </div>

        <hr class="border-slate-100" />

        <!-- ── Plus d'options (Référence + Notes + Documents) ─────── -->
        <MoreOptionsSection
            :reference="contractReference"
            :notes="notes"
            :files-count="pendingFiles.length"
            @update:reference="contractReference = $event"
            @update:notes="notes = $event"
            @open-documents="documentsModalOpen = true"
        />

        <!-- ── Récap fiscal + CTA ─────────────────────────────────── -->
        <div
            v-if="hasRange && selectedCompanyId !== null"
            class="flex flex-col gap-3 border-t border-slate-100 pt-5"
        >
            <FiscalPreviewCard
                :preview="preview"
                :loading="previewLoading"
                :year="fiscalYear"
            />
            <Button
                type="button"
                block
                :loading="submitting"
                :disabled="!canSubmit"
                @click="submit"
            >
                Créer la location
            </Button>
        </div>
        <div
            v-else
            class="flex flex-col gap-3 border-t border-slate-100 pt-5"
        >
            <p class="text-xs text-slate-500">
                Choisis une entreprise et une plage de dates pour voir les taxes induites.
            </p>
            <Button
                type="button"
                block
                :loading="submitting"
                :disabled="!canSubmit"
                @click="submit"
            >
                Créer la location
            </Button>
        </div>

        <ContractDocumentsModal
            v-model:open="documentsModalOpen"
            :files="pendingFiles"
            @update:files="pendingFiles = $event"
        />
    </section>
</template>

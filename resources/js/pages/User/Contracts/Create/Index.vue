<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText, X } from 'lucide-vue-next';
import { computed, ref, toRef } from 'vue';
import ContractRecapCard from '@/Components/Domain/Contract/ContractRecapCard.vue';
import FiscalDetailModal from '@/Components/Domain/Contract/FiscalDetailModal.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import { useContractFiscalPreview } from '@/Composables/Contract/useContractFiscalPreview';
import { useContractForm } from '@/Composables/Contract/useContractForm';
import { storePendingDocuments } from '@/Composables/Contract/useContractFormPendingDocuments';
import { useToasts } from '@/Composables/Shared/useToasts';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { indexById } from '@/Utils/Common/indexById';
import { computeContractDurationDays } from '@/Utils/Contract/contractDuration';
import ContractFormFields from './partials/ContractFormFields.vue';

const toasts = useToasts();

const props = defineProps<{
    /**
     * Options SLIM (S2.5) · zéro pipeline fiscal au mount. La taxe
     * pleine du véhicule sélectionné est calculée à la volée via
     * l'endpoint AJAX `GET /app/vehicles/{vehicle}/full-year-tax`
     * dans `ContractFormFields` (composable `useVehicleFullYearTax`).
     */
    options: {
        vehicles: App.Data.User.Vehicle.VehicleFilterOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
    busyDatesByVehicleId: Record<number, string[]>;
}>();

const MAX_DOCUMENTS = 5;
const MAX_SIZE_BYTES = 10 * 1024 * 1024;

const { form, canSubmit, submit } = useContractForm();
const pendingFiles = ref<File[]>([]);

// ── Recap card live ──────────────────────────────────────────────────
const vehicleById = computed(() => indexById(props.options.vehicles));
const companyById = computed(() => indexById(props.options.companies));

const recapVehicle = computed(() => {
    if (form.vehicle_id === null) {
return null;
}

    const v = vehicleById.value.get(form.vehicle_id);

    if (!v) {
return null;
}

    return { plate: v.licensePlate, label: v.label };
});

const recapCompany = computed(() => {
    if (form.company_id === null) {
return null;
}

    return companyById.value.get(form.company_id) ?? null;
});

const recapDuration = computed<number | null>(() =>
    computeContractDurationDays(form.start_date, form.end_date),
);

const recapType = computed<'lcd' | 'lld' | null>(() => {
    if (recapDuration.value === null) {
return null;
}

    return recapDuration.value <= 30 ? 'lcd' : 'lld';
});

// ── Fiscal preview live ─────────────────────────────────────────────
const { preview, loading: previewLoading } = useContractFiscalPreview({
    vehicleId: toRef(form, 'vehicle_id'),
    companyId: toRef(form, 'company_id'),
    startDate: toRef(form, 'start_date'),
    endDate: toRef(form, 'end_date'),
});

const fiscalDetailOpen = ref<boolean>(false);

// ── Documents pending ───────────────────────────────────────────────
function onFilesAdded(files: File[]): void {
    const remaining = MAX_DOCUMENTS - pendingFiles.value.length;
    pendingFiles.value = [...pendingFiles.value, ...files.slice(0, remaining)];
}

function removePendingFile(index: number): void {
    pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
}

async function submitWithDocuments(): Promise<void> {
    // Sérialise les fichiers dans sessionStorage avant le submit Inertia.
    // La page Show post-redirect les uploadera en arrière-plan.
    if (pendingFiles.value.length > 0) {
        await storePendingDocuments(pendingFiles.value);
    }

    submit();
}
</script>

<template>
    <Head title="Nouvelle location" />

    <UserLayout>
        <div class="m-auto flex max-w-[80em] flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link
                    :href="contractsIndexRoute.url()"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
                >
                    <ArrowLeft :size="14" :stroke-width="1.75" />
                    Retour aux locations
                </Link>
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Nouvelle location
                    </h1>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Form (2/3 ≥ lg) -->
                <form
                    class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6 xl:col-span-2"
                    @submit.prevent="submitWithDocuments"
                >
                    <ContractFormFields
                        :form="form"
                        :options="props.options"
                        :busy-dates-by-vehicle-id="props.busyDatesByVehicleId"
                    />

                    <hr class="border-slate-100" />

                    <!-- Documents (sous-section de DÉTAILS) -->
                    <section class="flex flex-col gap-3">
                        <p class="eyebrow">Documents</p>
                        <p class="text-xs text-slate-500">
                            Joindre jusqu'à {{ MAX_DOCUMENTS }} PDF (10 Mo max chacun).
                            Les documents seront uploadés après la création.
                        </p>

                        <ul
                            v-if="pendingFiles.length > 0"
                            class="flex flex-col gap-2"
                        >
                            <li
                                v-for="(file, idx) in pendingFiles"
                                :key="idx"
                                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"
                            >
                                <FileText
                                    :size="20"
                                    :stroke-width="1.75"
                                    class="shrink-0 text-rose-500"
                                    aria-hidden="true"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">
                                        {{ file.name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ (file.size / (1024 * 1024)).toFixed(1) }} Mo
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                                    :title="`Retirer ${file.name}`"
                                    :aria-label="`Retirer ${file.name}`"
                                    @click="removePendingFile(idx)"
                                >
                                    <X :size="14" :stroke-width="1.75" />
                                </button>
                            </li>
                        </ul>

                        <DocumentDropZone
                            v-if="pendingFiles.length < MAX_DOCUMENTS"
                            accept="application/pdf"
                            :max-size-bytes="MAX_SIZE_BYTES"
                            :max-files="MAX_DOCUMENTS - pendingFiles.length"
                            multiple
                            @files-added="onFilesAdded"
                @rejected="(msg: string) => toasts.push({ tone: 'error', title: 'Fichier rejeté', description: msg })"
                        />
                    </section>

                    <!-- Recap card collapsible : visible < xl uniquement,
                         juste avant les actions pour relire avant submit. -->
                    <ContractRecapCard
                        class="xl:hidden"
                        collapsible
                        :vehicle="recapVehicle"
                        :company="recapCompany"
                        :start-date="form.start_date"
                        :end-date="form.end_date"
                        :duration-days="recapDuration"
                        :contract-type="recapType"
                        :drivers-count="form.driver_ids.length"
                        :preview="preview"
                        :preview-loading="previewLoading"
                        @open-detail="fiscalDetailOpen = true"
                    />

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Link :href="contractsIndexRoute.url()">
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :disabled="!canSubmit"
                        >
                            Enregistrer la location
                        </Button>
                    </div>
                </form>

                <!-- Recap card sticky aside ≥ xl : toujours déployée, à droite. -->
                <aside class="hidden xl:order-last xl:block">
                    <div class="xl:sticky xl:top-6">
                        <ContractRecapCard
                            :vehicle="recapVehicle"
                            :company="recapCompany"
                            :start-date="form.start_date"
                            :end-date="form.end_date"
                            :duration-days="recapDuration"
                            :contract-type="recapType"
                            :drivers-count="form.driver_ids.length"
                            :preview="preview"
                            :preview-loading="previewLoading"
                            @open-detail="fiscalDetailOpen = true"
                        />
                    </div>
                </aside>
            </div>
        </div>

        <FiscalDetailModal
            v-model:open="fiscalDetailOpen"
            :preview="preview"
            :company-short-code="recapCompany?.shortCode ?? ''"
        />
    </UserLayout>
</template>

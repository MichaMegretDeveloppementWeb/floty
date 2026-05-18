<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, ref, toRef } from 'vue';
import ContractRecapCard from '@/Components/Domain/Contract/ContractRecapCard.vue';
import FiscalDetailModal from '@/Components/Domain/Contract/FiscalDetailModal.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useContractFiscalPreview } from '@/Composables/Contract/useContractFiscalPreview';
import { useContractForm } from '@/Composables/Contract/useContractForm';
import { useContractRentalPreview } from '@/Composables/Contract/useContractRentalPreview';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import { computeContractDurationDays } from '@/Utils/Contract/contractDuration';
import ContractFormFields from '../Create/partials/ContractFormFields.vue';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
    /**
     * Options SLIM (S2.5) · zéro pipeline fiscal au mount. La taxe
     * pleine du véhicule sélectionné est calculée à la volée via
     * l'endpoint AJAX dans `ContractFormFields`.
     */
    options: {
        vehicles: App.Data.User.Vehicle.VehicleFilterOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
    };
    busyDatesByVehicleId: Record<number, string[]>;
}>();

const { form, canSubmit, submit } = useContractForm(props.contract);

// ── Recap card live ──────────────────────────────────────────────────
const vehicleById = computed(() => {
    const map = new Map<number, App.Data.User.Vehicle.VehicleFilterOptionData>();

    for (const v of props.options.vehicles) {
map.set(v.id, v);
}

    return map;
});

const companyById = computed(() => {
    const map = new Map<number, App.Data.User.Company.CompanyOptionData>();

    for (const c of props.options.companies) {
map.set(c.id, c);
}

    return map;
});

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
// Calcul standalone : seule la durée du contrat (start/end) détermine
// le coût fiscal. Pas de cumul ni d'exclusion à faire.
const { preview, loading: previewLoading } = useContractFiscalPreview({
    vehicleId: toRef(form, 'vehicle_id'),
    companyId: toRef(form, 'company_id'),
    startDate: toRef(form, 'start_date'),
    endDate: toRef(form, 'end_date'),
});

// ── Rental preview live (SC4 · loyer induit) ────────────────────────
const { preview: rentalPreview, loading: rentalPreviewLoading } = useContractRentalPreview({
    vehicleId: toRef(form, 'vehicle_id'),
    companyId: toRef(form, 'company_id'),
    startDate: toRef(form, 'start_date'),
    endDate: toRef(form, 'end_date'),
});

const fiscalDetailOpen = ref<boolean>(false);
</script>

<template>
    <Head :title="`Modifier location ${props.contract.vehicleLicensePlate}`" />

    <UserLayout>
        <div class="m-auto flex max-w-[80em] flex-col gap-6">
            <header class="flex flex-col gap-3">
                <Link
                    :href="contractsShowRoute.url({ contract: props.contract.id })"
                    class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
                >
                    <ArrowLeft :size="14" :stroke-width="1.75" />
                    Retour à la location
                </Link>
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Modifier la location #{{ props.contract.id }}
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        {{ props.contract.vehicleLicensePlate }} ·
                        {{ props.contract.companyShortCode }}
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Form (2/3 ≥ lg) -->
                <form
                    class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6 xl:col-span-2"
                    @submit.prevent="submit"
                >
                    <ContractFormFields
                        :form="form"
                        :options="props.options"
                        :busy-dates-by-vehicle-id="props.busyDatesByVehicleId"
                    />

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
                        :rental-preview="rentalPreview"
                        :rental-preview-loading="rentalPreviewLoading"
                        @open-detail="fiscalDetailOpen = true"
                    />

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Link
                            :href="contractsShowRoute.url({ contract: props.contract.id })"
                        >
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            :loading="form.processing"
                            :disabled="!canSubmit"
                        >
                            Mettre à jour la location
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
                            :rental-preview="rentalPreview"
                            :rental-preview-loading="rentalPreviewLoading"
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

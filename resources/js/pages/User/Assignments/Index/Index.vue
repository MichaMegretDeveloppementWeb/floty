<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useAssignmentPageLogic } from '@/Composables/Assignment/useAssignmentPageLogic';
import { index as planningIndexRoute } from '@/routes/user/planning';
import AssignmentForm from './partials/AssignmentForm.vue';
import FiscalRecapCard from './partials/FiscalRecapCard.vue';

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.VehicleOptionData[];
    companies: App.Data.User.Company.CompanyOptionData[];
}>();

const logic = useAssignmentPageLogic(props.vehicles, props.companies);

async function handleSubmit(): Promise<void> {
    const ok = await logic.submit();

    if (ok) {
        router.visit(planningIndexRoute.url());
    }
}
</script>

<template>
    <Head title="Attribution rapide" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Planning</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Attribution rapide · {{ logic.fiscalYear.value }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Un véhicule, une entreprise, un ou plusieurs jours — tout en
                    une passe. Pour une attribution contextuelle à partir d'une
                    semaine précise, utilisez plutôt la vue d'ensemble et
                    cliquez sur la cellule voulue.
                </p>
            </header>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
                <AssignmentForm
                    :vehicles="vehicles"
                    :companies="companies"
                    :fiscal-year="logic.fiscalYear.value"
                    :selected-vehicle-id="logic.vehicleId.value"
                    :selected-company-id="logic.companyId.value"
                    :selected-dates="logic.dates.value"
                    :disabled-dates="logic.disabledDates.value"
                    :pair-dates="logic.pairDatesForCouple.value"
                    @update:selected-vehicle-id="logic.vehicleId.value = $event"
                    @update:selected-company-id="logic.companyId.value = $event"
                    @update:selected-dates="logic.dates.value = $event"
                />
                <FiscalRecapCard
                    :selected-vehicle-label="logic.selectedVehicleLabel.value"
                    :selected-company-label="logic.selectedCompanyLabel.value"
                    :selected-dates="logic.dates.value"
                    :preview="logic.preview.value"
                    :preview-loading="logic.previewLoading.value"
                    :submitting="logic.submitting.value"
                    :can-submit="logic.canSubmit.value"
                    @submit="handleSubmit"
                />
            </div>
        </div>
    </UserLayout>
</template>

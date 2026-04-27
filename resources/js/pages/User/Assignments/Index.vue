<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useAssignmentForm } from '@/Composables/Assignment/useAssignmentForm';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';
import { useVehicleAvailability } from '@/Composables/Planning/useVehicleAvailability';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import AssignmentForm from '@/pages/User/Assignments/partials/AssignmentForm.vue';
import FiscalRecapCard from '@/pages/User/Assignments/partials/FiscalRecapCard.vue';
import { index as planningIndexRoute } from '@/routes/user/planning';

defineProps<{
    vehicles: App.Data.User.Vehicle.VehicleOptionData[];
    companies: App.Data.User.Company.CompanyOptionData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();

const form = useAssignmentForm();
const availability = useVehicleAvailability();
const fiscalPreview = useFiscalPreview();

// Charge l'occupation du véhicule à chaque sélection.
watch(form.vehicleId, async (vehicleId) => {
    form.dates.value = [];
    fiscalPreview.reset();
    availability.reset();

    if (vehicleId === null) {
        return;
    }

    await availability.load(vehicleId, fiscalYear.value);
});

// Re-déclenche le preview à chaque changement de couple ou dates.
watch(
    () => [form.vehicleId.value, form.companyId.value, form.dates.value] as const,
    ([vehicleId, companyId, dates]) => {
        fiscalPreview.fetch({ vehicleId, companyId, dates });
    },
    { deep: true },
);

// Calendrier : on grise les jours occupés SAUF ceux déjà attribués au
// couple courant (on les ré-affiche dans un état « existant »).
const disabledDates = computed((): string[] => {
    const pairSet = new Set(availability.pairDatesFor(form.companyId.value));

    return availability.busyDates.value.filter((d) => !pairSet.has(d));
});

const pairDatesForCouple = computed((): string[] =>
    availability.pairDatesFor(form.companyId.value),
);

async function handleSubmit(): Promise<void> {
    const ok = await form.submit();

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
                    Attribution rapide · {{ fiscalYear }}
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
                    :fiscal-year="fiscalYear"
                    :selected-vehicle-id="form.vehicleId.value"
                    :selected-company-id="form.companyId.value"
                    :selected-dates="form.dates.value"
                    :disabled-dates="disabledDates"
                    :pair-dates="pairDatesForCouple"
                    @update:selected-vehicle-id="form.vehicleId.value = $event"
                    @update:selected-company-id="form.companyId.value = $event"
                    @update:selected-dates="form.dates.value = $event"
                />
                <FiscalRecapCard
                    :vehicles="vehicles"
                    :companies="companies"
                    :selected-vehicle-id="form.vehicleId.value"
                    :selected-company-id="form.companyId.value"
                    :selected-dates="form.dates.value"
                    :preview="fiscalPreview.preview.value"
                    :preview-loading="fiscalPreview.loading.value"
                    :submitting="form.submitting.value"
                    :can-submit="form.canSubmit.value"
                    @submit="handleSubmit"
                />
            </div>
        </div>
    </UserLayout>
</template>

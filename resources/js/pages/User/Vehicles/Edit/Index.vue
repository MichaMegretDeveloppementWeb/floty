<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { useVehicleEditForm } from '@/Composables/Vehicle/Edit/useVehicleEditForm';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import FiscalChangeMetadataSection from './partials/FiscalChangeMetadataSection.vue';
import FiscalCharacteristicsSection from './partials/FiscalCharacteristicsSection.vue';
import IdentitySection from './partials/IdentitySection.vue';
import RegistrationSection from './partials/RegistrationSection.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const {
    form,
    changeReasonOptions,
    isOtherChange,
    hasFiscalChanges,
    canSubmit,
    versionsToBeDeleted,
    cascadeConfirmOpen,
    requestSubmit,
    confirmSubmit,
} = useVehicleEditForm(props);

const cascadeMessage = (): string => {
    const count = versionsToBeDeleted.value.length;
    const versionsList = versionsToBeDeleted.value
        .map((v) => `du ${formatDateFr(v.effectiveFrom)}${v.effectiveTo ? ` au ${formatDateFr(v.effectiveTo)}` : ' (courante)'}`)
        .join(' · ');

    return `Cette opération va supprimer ${count} version${count > 1 ? 's' : ''} d'historique fiscal postérieure${count > 1 ? 's' : ''} à la date d'effet choisie : ${versionsList}. Cette action est irréversible.`;
};
</script>

<template>
    <Head :title="`Modifier ${props.vehicle.licensePlate}`" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Flotte</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Modifier {{ props.vehicle.brand }} {{ props.vehicle.model }} ({{ props.vehicle.licensePlate }})
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Identité du véhicule + nouvelle version des caractéristiques fiscales.
                </p>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="requestSubmit"
            >
                <IdentitySection :form="form" />
                <RegistrationSection :form="form" />
                <FiscalCharacteristicsSection :form="form" :options="props.options" />
                <FiscalChangeMetadataSection
                    :form="form"
                    :change-reason-options="changeReasonOptions"
                    :is-other-change="isOtherChange"
                    :has-fiscal-changes="hasFiscalChanges"
                />

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <Link :href="vehiclesShowRoute.url({ vehicle: props.vehicle.id })">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="!canSubmit"
                    >
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>

        <ConfirmModal
            v-model:open="cascadeConfirmOpen"
            title="Confirmer la cascade rétroactive"
            :message="cascadeMessage()"
            confirm-label="Confirmer la suppression"
            tone="danger"
            @confirm="confirmSubmit"
        />
    </UserLayout>
</template>

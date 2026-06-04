<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import type { VehicleOption } from '@/Components/Domain/RentalDiscount/VehiclesMultiPicker.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useRentalDiscountForm } from '@/Composables/RentalDiscount/Form/useRentalDiscountForm';
import { show as showRoute, update as updateRoute } from '@/routes/user/rental-discounts';
import RentalDiscountFormFields from '../Create/partials/RentalDiscountFormFields.vue';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
    vehicles: VehicleOption[];
}>();

const {
    form,
    discountPercent,
    appliesToAllVehicles,
    range,
    ongoing,
    pickerInitialYear,
    pickerInitialMonth,
    conflicts,
    canSubmit,
    submit,
} = useRentalDiscountForm(
    {
        id: props.rentalDiscount.id,
        companyId: props.rentalDiscount.companyId,
        startDate: props.rentalDiscount.startDate,
        endDate: props.rentalDiscount.endDate,
        discountPercent: props.rentalDiscount.discountBasisPoints / 100,
        label: props.rentalDiscount.label,
        notes: props.rentalDiscount.notes,
        vehicleIds: props.rentalDiscount.vehicles.map((v) => v.id),
    },
    (form) => {
        form.patch(updateRoute.url({ rentalDiscount: props.rentalDiscount.id }));
    },
);

// Single synthesized option for the locked SearchableSelect; the full
// company list is not loaded in Edit mode.
const companyOptionsForLock = [
    {
        id: props.rentalDiscount.companyId,
        shortCode: props.rentalDiscount.companyShortCode,
        legalName: props.rentalDiscount.companyLegalName,
        color: props.rentalDiscount.companyColor,
    },
];
</script>

<template>
    <Head :title="`Modifier ${props.rentalDiscount.label ?? 'la réduction'}`" />

    <UserLayout>
        <form class="flex flex-col gap-6 max-w-3xl m-auto w-full" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <Link
                    :href="showRoute.url({ rentalDiscount: rentalDiscount.id })"
                    class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ChevronLeft :size="14" :stroke-width="1.75" />
                    Retour à la réduction
                </Link>
                <h1 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                    Modifier la réduction
                </h1>
                <p class="text-sm text-slate-500">
                    Ajustez la période, le pourcentage ou la liste des véhicules ciblés. L'entreprise reste figée.
                </p>
            </div>

            <RentalDiscountFormFields
                :form="form"
                :companies="companyOptionsForLock"
                :vehicles="props.vehicles"
                :is-edit="true"
                :discount-percent="discountPercent"
                :applies-to-all-vehicles="appliesToAllVehicles"
                :range="range"
                :ongoing="ongoing"
                :picker-initial-year="pickerInitialYear"
                :picker-initial-month="pickerInitialMonth"
                :conflicts="conflicts"
                @update:discount-percent="discountPercent = $event"
                @update:applies-to-all-vehicles="appliesToAllVehicles = $event"
                @update:range="range = $event"
                @update:ongoing="ongoing = $event"
                @update:company-id="form.company_id = $event"
                @update:label="form.label = $event"
                @update:notes="form.notes = $event"
                @update:vehicle-ids="form.vehicle_ids = $event"
            />

            <div class="flex items-center justify-end gap-3">
                <Link :href="showRoute.url({ rentalDiscount: rentalDiscount.id })">
                    <Button variant="ghost" type="button">
                        Annuler
                    </Button>
                </Link>
                <Button
                    type="submit"
                    :disabled="!canSubmit"
                    :loading="form.processing"
                >
                    Enregistrer les modifications
                </Button>
            </div>
        </form>
    </UserLayout>
</template>

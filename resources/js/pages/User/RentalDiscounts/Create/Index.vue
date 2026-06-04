<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import type { VehicleOption } from '@/Components/Domain/RentalDiscount/VehiclesMultiPicker.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useRentalDiscountForm } from '@/Composables/RentalDiscount/Form/useRentalDiscountForm';
import { index as indexRoute, store as storeRoute } from '@/routes/user/rental-discounts';
import RentalDiscountFormFields from './partials/RentalDiscountFormFields.vue';

type CompanyOption = {
    id: number;
    shortCode: string;
    legalName: string;
    color: string;
};

const props = defineProps<{
    companies: CompanyOption[];
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
        id: null,
        companyId: null,
        startDate: '',
        endDate: '',
        discountPercent: 5,
        label: null,
        notes: null,
        vehicleIds: [],
    },
    (form) => {
        form.post(storeRoute.url());
    },
);
</script>

<template>
    <Head title="Nouvelle réduction commerciale" />

    <UserLayout>
        <form class="flex flex-col gap-6 max-w-3xl m-auto w-full" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <Link
                    :href="indexRoute.url()"
                    class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ChevronLeft :size="14" :stroke-width="1.75" />
                    Réductions commerciales
                </Link>
                <h1 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                    Nouvelle réduction commerciale
                </h1>
                <p class="text-sm text-slate-500">
                    Définissez un pourcentage de remise applicable au loyer mensuel d'une entreprise sur une période donnée.
                </p>
            </div>

            <RentalDiscountFormFields
                :form="form"
                :companies="props.companies"
                :vehicles="props.vehicles"
                :is-edit="false"
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
            />

            <div class="flex items-center justify-end gap-3">
                <Link :href="indexRoute.url()">
                    <Button variant="ghost" type="button">
                        Annuler
                    </Button>
                </Link>
                <Button
                    type="submit"
                    :disabled="!canSubmit"
                    :loading="form.processing"
                >
                    Créer la réduction
                </Button>
            </div>
        </form>
    </UserLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const vehiclesValue = computed<string>(() =>
    props.rentalDiscount.isAllVehicles
        ? 'Tous'
        : String(props.rentalDiscount.vehicles.length),
);

const vehiclesMeta = computed<string>(() => {
    if (props.rentalDiscount.isAllVehicles) {
        return 'véhicules concernés';
    }

    return `véhicule${props.rentalDiscount.vehicles.length > 1 ? 's' : ''} ciblé${props.rentalDiscount.vehicles.length > 1 ? 's' : ''}`;
});
</script>

<template>
    <div class="grid grid-cols-1 overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-3">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0">
            <p class="eyebrow mb-1.5">
                Véhicules
            </p>
            <p class="font-mono text-xl leading-none font-medium tracking-tight text-slate-900 tabular-nums">
                {{ vehiclesValue }}
            </p>
            <p class="mt-1.5 text-[11px] text-slate-500">
                {{ vehiclesMeta }}
            </p>
        </div>

        <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0">
            <p class="eyebrow mb-1.5">
                Créée par
            </p>
            <p class="truncate text-sm leading-tight font-medium text-slate-900">
                {{ rentalDiscount.createdByUserName ?? 'Système' }}
            </p>
            <p class="mt-1.5 text-[11px] text-slate-500">
                auteur de la réduction
            </p>
        </div>

        <div class="px-5 py-4">
            <p class="eyebrow mb-1.5">
                Créée le
            </p>
            <p class="font-mono text-sm leading-tight font-medium text-slate-900 tabular-nums">
                {{ formatDateFr(rentalDiscount.createdAt) }}
            </p>
            <p
                v-if="rentalDiscount.updatedAt && rentalDiscount.updatedAt !== rentalDiscount.createdAt"
                class="mt-1.5 text-[11px] text-slate-500"
            >
                modifiée le {{ formatDateFr(rentalDiscount.updatedAt) }}
            </p>
            <p v-else class="mt-1.5 text-[11px] text-slate-400">
                jamais modifiée
            </p>
        </div>
    </div>
</template>

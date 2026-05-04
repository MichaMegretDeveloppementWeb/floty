<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';
import { show as driversShowRoute } from '@/routes/user/drivers';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();

function driverInitials(): string {
    if (props.contract.driverFullName === null) {
        return '';
    }

    const parts = props.contract.driverFullName.trim().split(/\s+/);

    if (parts.length === 0) {
        return '';
    }

    const first = parts[0]?.[0] ?? '';
    const last = parts[parts.length - 1]?.[0] ?? '';

    return (first + last).toUpperCase();
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Link
            :href="vehiclesShowRoute.url({ vehicle: contract.vehicleId })"
            class="group flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        >
            <div class="flex flex-col gap-2">
                <span
                    class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                >
                    Véhicule
                </span>
                <div class="flex items-center gap-2">
                    <Plate :value="contract.vehicleLicensePlate" />
                    <span class="text-sm font-medium text-slate-900">
                        {{ contract.vehicleBrand }} {{ contract.vehicleModel }}
                    </span>
                </div>
            </div>
            <ChevronRight
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-slate-400 transition-transform duration-[120ms] ease-out group-hover:translate-x-0.5 group-hover:text-slate-600"
            />
        </Link>

        <Link
            :href="companiesShowRoute(contract.companyId).url"
            class="group flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        >
            <div class="flex flex-col gap-2">
                <span
                    class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                >
                    Entreprise utilisatrice
                </span>
                <CompanyTag
                    :name="contract.companyLegalName"
                    :initials="contract.companyShortCode"
                    :color="contract.companyColor"
                />
            </div>
            <ChevronRight
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-slate-400 transition-transform duration-[120ms] ease-out group-hover:translate-x-0.5 group-hover:text-slate-600"
            />
        </Link>

        <Link
            v-if="contract.driverId !== null"
            :href="driversShowRoute(contract.driverId).url"
            class="group flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        >
            <div class="flex flex-col gap-2">
                <span class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Conducteur
                </span>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                        {{ driverInitials() }}
                    </span>
                    <span class="text-sm font-medium text-slate-900">{{ contract.driverFullName }}</span>
                </div>
            </div>
            <ChevronRight
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-slate-400 transition-transform duration-[120ms] ease-out group-hover:translate-x-0.5 group-hover:text-slate-600"
            />
        </Link>
        <div
            v-else
            class="flex items-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
        >
            Aucun conducteur attribué.
        </div>
    </div>
</template>

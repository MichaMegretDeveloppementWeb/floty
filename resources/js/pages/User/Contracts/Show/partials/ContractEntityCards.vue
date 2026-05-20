<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';
import { show as driversShowRoute } from '@/routes/user/drivers';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';

defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
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
            :href="companiesShowRoute.url(contract.companyId)"
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
            v-if="contract.drivers.length === 1"
            :href="driversShowRoute.url(contract.drivers[0]!.id)"
            class="group flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        >
            <div class="flex flex-col gap-2">
                <span class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Conducteur
                </span>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                        {{ contract.drivers[0]!.initials }}
                    </span>
                    <span class="text-sm font-medium text-slate-900">{{ contract.drivers[0]!.fullName }}</span>
                </div>
            </div>
            <ChevronRight
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-slate-400 transition-transform duration-[120ms] ease-out group-hover:translate-x-0.5 group-hover:text-slate-600"
            />
        </Link>

        <div
            v-else-if="contract.drivers.length > 1"
            class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4"
        >
            <span class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                Conducteurs
            </span>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="d in contract.drivers"
                    :key="d.id"
                    :href="driversShowRoute.url(d.id)"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                >
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-semibold text-blue-700">
                        {{ d.initials }}
                    </span>
                    {{ d.fullName }}
                </Link>
            </div>
        </div>

        <div
            v-else
            class="flex items-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
        >
            Aucun conducteur attribué.
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';

defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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

        <!--
            TODO 05.X : envelopper dans <Link :href="companiesShowRoute.url(...)">
            quand la page Show Company sera livrée. Pour V1, carte statique.
        -->
        <div
            class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4"
        >
            <div class="flex flex-col gap-2">
                <span
                    class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                >
                    Entreprise utilisatrice
                </span>
                <CompanyTag
                    :name="contract.companyLegalName"
                    :initials="contract.companyShortCode.slice(0, 2)"
                    :color="contract.companyColor"
                />
            </div>
        </div>
    </div>
</template>

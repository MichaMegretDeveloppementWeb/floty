<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { contractTypeLabel } from '@/Utils/labels/contractEnumLabels';

defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
</script>

<template>
    <header class="flex flex-col gap-3">
        <Link
            :href="contractsIndexRoute.url()"
            class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
        >
            <ArrowLeft :size="14" :stroke-width="1.75" />
            Retour aux contrats
        </Link>
        <div class="flex flex-wrap items-center gap-3">
            <Plate :value="contract.vehicleLicensePlate" />
            <h1
                class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
            >
                {{ contract.vehicleBrand }} {{ contract.vehicleModel }}
            </h1>
            <span
                :class="[
                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                    contract.contractType === 'lcd'
                        ? 'bg-emerald-100 text-emerald-800'
                        : contract.contractType === 'lld'
                          ? 'bg-indigo-100 text-indigo-800'
                          : 'bg-slate-100 text-slate-700',
                ]"
            >
                {{ contractTypeLabel[contract.contractType] }}
            </span>
        </div>
        <p class="text-base text-slate-600">
            Attribué à
            <span class="font-semibold text-slate-900">
                {{ contract.companyLegalName }}
            </span>
            (<span class="font-mono">{{ contract.companyShortCode }}</span
            >)
        </p>
    </header>
</template>

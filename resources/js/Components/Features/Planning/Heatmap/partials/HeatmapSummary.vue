<script setup lang="ts">
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehiclesCount: number;
    totalDays: number;
    totalAnnualTax: number;
    fiscalYear: number;
}>();
</script>

<template>
    <div class="flex gap-6 text-sm">
        <div>
            <span class="text-slate-500">Flotte :</span>
            <span class="ml-1 font-medium text-slate-900">
                {{ vehiclesCount }} véhicules
            </span>
        </div>
        <div>
            <span class="text-slate-500">Jours-véhicule :</span>
            <span class="ml-1 font-medium text-slate-900">
                {{ totalDays.toLocaleString('fr-FR') }}
            </span>
        </div>
        <div>
            <span class="text-slate-500">Taxes totales {{ fiscalYear }} :</span>
            <span class="ml-1 font-mono font-medium text-slate-900">
                {{ formatEur(totalAnnualTax) }}
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    vehiclesCount: number;
    totalDays: number;
    /** null while the costs map has not been hydrated yet. */
    totalAnnualTax: number | null;
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
            <span
                v-if="totalAnnualTax !== null"
                class="ml-1 font-mono font-medium text-slate-900"
            >
                {{ formatEur(totalAnnualTax) }}
            </span>
            <span
                v-else
                class="skeleton-shimmer ml-1 inline-block h-3 w-20 rounded align-middle"
                aria-label="Calcul en cours"
            ></span>
        </div>
    </div>
</template>

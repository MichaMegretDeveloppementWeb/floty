<script setup lang="ts">
/** Stats strip above the Rental Discounts index: active / planned / expired counters + total. */
import { BadgePercent } from 'lucide-vue-next';

defineProps<{
    total: number;
    stats: { active: number; planned: number; expired: number };
}>();
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6">
        <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Total
            </p>
            <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                {{ total }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                réduction{{ total > 1 ? 's' : '' }} enregistrée{{ total > 1 ? 's' : '' }}
            </p>
        </div>
        <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Actives
            </p>
            <p class="inline-flex items-baseline gap-2 font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-emerald-700">
                <BadgePercent :size="16" :stroke-width="2" class="self-center" aria-hidden="true" />
                {{ stats.active }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                en cours d'application
            </p>
        </div>
        <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Planifiées
            </p>
            <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-amber-700">
                {{ stats.planned }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                à venir
            </p>
        </div>
        <div class="sm:px-6 sm:last:pr-0">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Expirées
            </p>
            <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-500">
                {{ stats.expired }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                période close
            </p>
        </div>
    </div>
</template>

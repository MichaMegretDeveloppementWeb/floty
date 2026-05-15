<script setup lang="ts">
/**
 * Rangée de 4 KPIs **Présent** · reflète l'année calendaire courante
 * uniquement (chantier η Phase 1, doctrine temporelle).
 *
 * Refonte design D5.10.W · stats row éditorial flush (4 colonnes
 * séparées par hairlines verticaux sur ≥ sm, 2×2 sur mobile · pattern
 * miroir des onglets Fiscalité / Facturation). Plus de StatCard avec
 * icônes · on harmonise sur le pattern unique des autres tabs.
 *
 * Spécificités ·
 *   - Si `kpiFiscalAvailable === false` (règles fiscales pas codées
 *     pour l'année courante), la KPI Taxes affiche un `·` neutre avec
 *     caption « Règles {YYYY} non implémentées » (cf. doctrine HD6
 *     « pas de règles ≠ pas de données »).
 *   - Montant loyer · somme des 12 facturations mensuelles de l'année,
 *     `null` si au moins un véhicule de la flotte a un pricing manquant.
 */
import { computed } from 'vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    kpiStats: App.Data.User.Company.CompanyYearStatsData;
    kpiYear: number;
    kpiFiscalAvailable: boolean;
}>();

const taxValue = computed<string>(() => {
    if (!props.kpiFiscalAvailable) {
        return '·';
    }

    return formatEur(props.kpiStats.annualTaxDue);
});

const taxCaption = computed<string>(() => {
    if (!props.kpiFiscalAvailable) {
        return `Règles ${props.kpiYear} non implémentées`;
    }

    return `année ${props.kpiYear}`;
});

const rentValue = computed<string>(() => {
    if (props.kpiStats.rent === null) {
        return '·';
    }

    return formatEur(props.kpiStats.rent);
});

const rentCaption = computed<string>(() => {
    if (props.kpiStats.rent === null) {
        return 'tarif véhicule manquant';
    }

    return `année ${props.kpiYear}`;
});
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 sm:gap-x-0 gap-y-6 sm:gap-y-0 border-y border-slate-100 py-6">
        <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Jours d'usage
            </p>
            <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                {{ props.kpiStats.daysUsed }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                année {{ props.kpiYear }}
            </p>
        </div>
        <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Locations
            </p>
            <p class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none text-slate-900">
                {{ props.kpiStats.contractsCount }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                actifs en {{ props.kpiYear }}
            </p>
        </div>
        <div class="sm:px-6 sm:not-last:border-r sm:border-slate-100">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Taxes dues
            </p>
            <p
                class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none"
                :class="props.kpiFiscalAvailable ? 'text-slate-900' : 'text-slate-400'"
            >
                {{ taxValue }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                {{ taxCaption }}
            </p>
        </div>
        <div class="sm:px-6 sm:last:pr-0">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                Montant loyer
            </p>
            <p
                class="font-mono text-[22px] font-medium tracking-tight tabular-nums leading-none"
                :class="props.kpiStats.rent !== null ? 'text-slate-900' : 'text-slate-400'"
            >
                {{ rentValue }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                {{ rentCaption }}
            </p>
        </div>
    </div>
</template>

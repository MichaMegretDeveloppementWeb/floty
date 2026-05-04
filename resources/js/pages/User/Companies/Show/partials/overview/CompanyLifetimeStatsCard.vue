<script setup lang="ts">
/**
 * Rangée de 4 KPIs cumulés « depuis le début » de l'entreprise — tous
 * exercices confondus. Pas de titre : la card est l'élément phare de
 * la fiche, juste sous le hero (chantier K, ADR-0020 D3).
 *
 * Le 4ᵉ KPI Loyer est un placeholder — la facturation arrive en V1.2,
 * `rentTotal` est `null` jusqu'à là.
 */
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    lifetime: App.Data.User.Company.CompanyLifetimeStatsData;
}>();
</script>

<template>
    <Card>
        <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ lifetime.daysUsed }}
                </p>
                <p class="text-xs text-slate-500">
                    jour{{ lifetime.daysUsed > 1 ? 's' : '' }} d'usage cumulés
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ lifetime.contractsCount }}
                </p>
                <p class="text-xs text-slate-500">
                    contrat{{ lifetime.contractsCount > 1 ? 's' : '' }} signé{{ lifetime.contractsCount > 1 ? 's' : '' }}
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">
                    {{ formatEur(lifetime.taxesGenerated) }}
                </p>
                <p class="text-xs text-slate-500">
                    de taxes générées
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <p
                    v-if="lifetime.rentTotal !== null"
                    class="text-2xl font-semibold text-slate-900 tabular-nums"
                >
                    {{ formatEur(lifetime.rentTotal) }}
                </p>
                <p
                    v-else
                    class="text-2xl font-semibold text-slate-400 tabular-nums"
                >
                    —
                </p>
                <p class="text-xs text-slate-500">
                    de loyers cumulés
                    <span class="ml-1 inline-block rounded bg-slate-100 px-1 py-px text-[10px] font-medium uppercase tracking-wide text-slate-500">
                        V1.2
                    </span>
                </p>
            </div>
        </div>
    </Card>
</template>

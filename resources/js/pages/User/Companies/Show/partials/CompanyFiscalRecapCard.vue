<script setup lang="ts">
/**
 * Carte recap fiscale d'une entreprise pour l'exercice sélectionné
 * (Phase 12 D5.9.A). Remplace l'ancienne paire « grosse carte
 * total + tableau breakdown véhicule » qui dédoublait l'info
 * disponible sur l'onglet Locations sans valeur narrative
 * supplémentaire.
 *
 * Présente :
 *   - Le total des taxes en avant + détail CO₂ / Polluants en ligne
 *     juste en dessous.
 *   - 3 stats compactes (jours cumulés, véhicules taxés, locations
 *     couvrant l'exercice).
 *   - Un lien contextuel vers l'onglet Locations filtré sur la
 *     période de l'année (cohérent avec le pattern d'URL utilisé
 *     par `CompanyContractsTab`).
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
    companyId: number;
}>();

const hasActivity = computed<boolean>(
    () => props.fiscal.totalDays > 0 || props.fiscal.contractsCount > 0,
);

const locationsHref = computed<string>(() => {
    const year = props.fiscal.year;

    return `/app/companies/${props.companyId}`
        + `?tab=contracts`
        + `&periodStart=${year}-01-01`
        + `&periodEnd=${year}-12-31`;
});

const vehiclesCount = computed<number>(() => props.fiscal.rows.length);
</script>

<template>
    <Card>
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Taxes {{ fiscal.year }}
                </p>
                <p class="font-mono text-3xl font-semibold tabular-nums text-slate-900">
                    {{ formatEur(fiscal.totalTaxAll) }}
                </p>
                <p class="text-xs text-slate-500">
                    CO₂ <span class="font-medium text-slate-700">{{ formatEur(fiscal.totalTaxCo2) }}</span>
                    <span class="mx-1.5 text-slate-300">·</span>
                    Polluants <span class="font-medium text-slate-700">{{ formatEur(fiscal.totalTaxPollutants) }}</span>
                </p>
            </div>

            <dl v-if="hasActivity" class="grid grid-cols-3 gap-3">
                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        Jours cumulés
                    </dt>
                    <dd class="mt-0.5 font-mono text-base font-semibold tabular-nums text-slate-900">
                        {{ fiscal.totalDays }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        Véhicules taxés
                    </dt>
                    <dd class="mt-0.5 font-mono text-base font-semibold tabular-nums text-slate-900">
                        {{ vehiclesCount }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        Locations
                    </dt>
                    <dd class="mt-0.5 font-mono text-base font-semibold tabular-nums text-slate-900">
                        {{ fiscal.contractsCount }}
                    </dd>
                </div>
            </dl>

            <p v-else class="text-sm italic text-slate-500">
                Aucune activité fiscale sur l'exercice {{ fiscal.year }}.
            </p>

            <Link
                v-if="hasActivity"
                :href="locationsHref"
                class="inline-flex w-fit cursor-pointer items-center gap-1 text-xs font-medium text-slate-600 underline-offset-2 transition-colors duration-[120ms] hover:text-slate-900 hover:underline"
            >
                Voir les locations {{ fiscal.year }}
                <ArrowRight :size="13" :stroke-width="1.75" />
            </Link>
        </div>
    </Card>
</template>

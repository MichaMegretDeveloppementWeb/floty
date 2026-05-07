<script setup lang="ts">
/**
 * Récap 12 mois × {jours utilisés, montant HT} pour la facturation
 * d'une entité (véhicule ou entreprise) · Phase 14.D V1.2.
 *
 * Composant **stateless / présentationnel** : aucune logique de
 * récupération, on consomme simplement le DTO `MonthlyBillingBreakdownData`
 * fourni par le parent. Les conversions cents → € et la mise en forme
 * FR sont faites localement.
 *
 * Les mois bloqués (`hasMissingPricing = true`) affichent une ligne
 * grisée avec un libellé explicite invitant à renseigner les tarifs
 * sur la fiche véhicule. Les autres lignes affichent le total formaté
 * en euros, clavier-friendly et en tabular-nums (lecture verticale).
 */
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

type MonthlyBilling = App.Data.User.Billing.MonthlyBillingBreakdownData;
type Entry = MonthlyBilling['entries'][number];

const props = defineProps<{
    /** Récap pré-calculé par le backend pour l'année concernée. */
    monthlyBilling: MonthlyBilling;
    /**
     * Légende dans le header. Permet d'adapter le wording au contexte
     * (ex. « Recettes mensuelles » sur véhicule vs « Facturation
     * mensuelle » sur entreprise).
     */
    title: string;
    /** Sous-titre court sous le titre (optionnel). */
    description?: string;
}>();

const MONTH_LABELS = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
] as const;

const totalLabel = computed<string>(() => {
    if (props.monthlyBilling.yearTotalCents === null) {
        return 'Total indisponible';
    }

    return formatEur(props.monthlyBilling.yearTotalCents / 100, 2);
});

function entryHasDivergence(entry: Entry): boolean {
    if (entry.existingInvoiceId === null || entry.invoicedDaysUsed === null) {
        return false;
    }

    if (entry.daysUsed !== entry.invoicedDaysUsed) {
        return true;
    }

    return (
        entry.totalCents !== null
        && entry.invoicedTotalCents !== null
        && entry.totalCents !== entry.invoicedTotalCents
    );
}

function divergenceTooltip(entry: Entry): string {
    const invoicedDays = entry.invoicedDaysUsed ?? 0;
    const invoicedTotal = formatEur((entry.invoicedTotalCents ?? 0) / 100, 2);
    const currentTotal = formatEur((entry.totalCents ?? 0) / 100, 2);

    return (
        `Données obsolètes. Facture émise sur ${invoicedDays} j / ${invoicedTotal}. `
        + `Recalcul actuel : ${entry.daysUsed} j / ${currentTotal}. `
        + 'Régénérez pour mettre à jour avec les données actuelles.'
    );
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        {{ title }} {{ monthlyBilling.year }}
                    </h2>
                    <p v-if="description" class="mt-0.5 text-xs text-slate-500">
                        {{ description }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-medium tracking-wider uppercase text-slate-500">
                        Total annuel
                    </p>
                    <p
                        class="font-mono text-base font-semibold tabular-nums whitespace-nowrap"
                        :class="monthlyBilling.yearTotalCents === null
                            ? 'text-slate-400 italic'
                            : 'text-slate-900'"
                    >
                        {{ totalLabel }}
                    </p>
                    <p class="font-mono text-xs text-slate-500 tabular-nums whitespace-nowrap">
                        {{ monthlyBilling.yearTotalDaysUsed }} jour{{ monthlyBilling.yearTotalDaysUsed > 1 ? 's' : '' }} utilisé{{ monthlyBilling.yearTotalDaysUsed > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium tracking-wider uppercase text-slate-500">
                        <th class="py-2 pr-3 font-medium">Mois</th>
                        <th class="py-2 px-3 font-medium text-right">Jours utilisés</th>
                        <th class="py-2 px-3 font-medium text-right">Montant HT</th>
                        <th class="py-2 px-3 font-medium">N° facture</th>
                        <th v-if="$slots['row-actions']" class="py-2 pl-3 font-medium text-right">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="(entry, idx) in monthlyBilling.entries"
                        :key="entry.month"
                        :class="entry.hasMissingPricing
                            ? 'bg-amber-50/40 text-amber-700'
                            : entry.daysUsed === 0
                                ? 'text-slate-400'
                                : 'text-slate-800'"
                    >
                        <td class="py-2 pr-3 text-sm whitespace-nowrap">
                            {{ MONTH_LABELS[idx] }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono tabular-nums whitespace-nowrap">
                            {{ entry.daysUsed }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono tabular-nums whitespace-nowrap">
                            <template v-if="entry.hasMissingPricing">
                                <span class="text-xs italic">Tarif manquant</span>
                            </template>
                            <template v-else-if="entry.daysUsed === 0">
                                ·
                            </template>
                            <template v-else>
                                {{ formatEur((entry.totalCents ?? 0) / 100, 2) }}
                            </template>
                        </td>
                        <td class="py-2 px-3 whitespace-nowrap">
                            <template v-if="entry.existingInvoiceNumber">
                                <span class="inline-flex items-center gap-1.5">
                                    <AlertTriangle
                                        v-if="entryHasDivergence(entry)"
                                        :title="divergenceTooltip(entry)"
                                        class="shrink-0 text-amber-500"
                                        :size="14"
                                        :stroke-width="2"
                                    />
                                    <span class="font-mono text-xs text-slate-600">
                                        {{ entry.existingInvoiceNumber }}
                                    </span>
                                </span>
                            </template>
                        </td>
                        <td v-if="$slots['row-actions']" class="py-2 pl-3 text-right">
                            <slot name="row-actions" :entry="entry" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p
            v-if="monthlyBilling.hasAnyMissingPricing"
            class="mt-4 rounded-lg bg-amber-50/60 px-3 py-2 text-xs text-amber-800"
        >
            Un ou plusieurs mois ne peuvent pas être chiffrés faute de
            tarif annuel renseigné sur les véhicules concernés. Renseignez
            les tarifs depuis la fiche véhicule pour débloquer le calcul.
        </p>
    </Card>
</template>

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
 *
 * **Lot 3 réductions commerciales** · si au moins un mois est concerné
 * par une réduction (`yearTotalDiscountCentsPartial > 0`), la table
 * bascule sur une présentation à 3 colonnes monétaires
 * (Brut / Réduction / Net) pour exposer le détail de l'économie.
 * Sinon, présentation simple inchangée (1 colonne Montant HT). Le
 * comportement est auto-détecté · aucun flag manuel à propager depuis
 * le parent.
 */
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import {
    divergenceTooltip,
    entryHasDivergence,
} from '@/Utils/Billing/divergenceDetection';
import { formatEur } from '@/Utils/format/formatEur';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

type MonthlyBilling = App.Data.User.Billing.MonthlyBillingBreakdownData;

const props = withDefaults(
    defineProps<{
        /** Récap pré-calculé par le backend pour l'année concernée. */
        monthlyBilling: MonthlyBilling;
        /**
         * Légende dans le header. Permet d'adapter le wording au contexte
         * (ex. « Recettes mensuelles » sur véhicule vs « Facturation
         * mensuelle » sur entreprise). Ignoré si `unwrapped = true`.
         */
        title?: string;
        /** Sous-titre court sous le titre (optionnel). Ignoré si `unwrapped`. */
        description?: string;
        /**
         * Affiche la colonne « N° facture » (desktop) ou la pastille de
         * numéro (mobile). Sur la table véhicule, une ligne = un mois
         * cross-entreprises et peut donc référencer N factures différentes :
         * la colonne perd son sens · on la désactive depuis le parent.
         */
        showInvoiceNumberColumn?: boolean;
        /**
         * Refonte D5.10.W · mode « flush » sans Card wrapping ni header
         * interne (titre + total annuel). Utilisé par les nouveaux
         * tabs éditoriaux où le header est porté par le tab parent
         * pour rester aligné sur la hiérarchie typographique globale.
         */
        unwrapped?: boolean;
    }>(),
    { showInvoiceNumberColumn: true, unwrapped: false, title: '' },
);

const totalLabel = computed<string>(() => {
    if (props.monthlyBilling.yearTotalCents === null) {
        return 'Total indisponible';
    }

    return formatEur(props.monthlyBilling.yearTotalCents / 100, 2);
});

// T11 / E.17 : total partiel rendu en complément quand au moins un mois
// est bloqué par un tarif manquant. Donne une vue honnête du chiffré
// même si le total exact reste indisponible.
const partialTotalLabel = computed<string | null>(() => {
    if (!props.monthlyBilling.hasAnyMissingPricing) {
        return null;
    }

    return formatEur(props.monthlyBilling.yearTotalCentsPartial / 100, 2);
});

/**
 * Vrai dès qu'au moins un mois a appliqué une réduction commerciale ·
 * détermine la présentation 3 colonnes (brut/réduction/net) vs simple.
 */
const hasAnyDiscount = computed<boolean>(
    () => props.monthlyBilling.yearTotalDiscountCentsPartial > 0,
);
</script>

<template>
    <component :is="unwrapped ? 'div' : Card">
        <template v-if="!unwrapped" #header>
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
                    <p
                        v-if="partialTotalLabel !== null"
                        class="font-mono text-xs text-amber-700 tabular-nums whitespace-nowrap"
                        title="Somme des mois chiffrés (mois sans tarif exclus)"
                    >
                        Partiel : {{ partialTotalLabel }}
                    </p>
                    <p class="font-mono text-xs text-slate-500 tabular-nums whitespace-nowrap">
                        {{ monthlyBilling.yearTotalDaysUsed }} jour{{ monthlyBilling.yearTotalDaysUsed > 1 ? 's' : '' }} utilisé{{ monthlyBilling.yearTotalDaysUsed > 1 ? 's' : '' }}
                    </p>
                </div>
            </div>
        </template>

        <!-- Desktop ≥ md : table classique -->
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Récap mensuel facturation {{ monthlyBilling.year }}
                </caption>
                <thead>
                    <tr class="text-left text-xs font-medium tracking-wider uppercase text-slate-500">
                        <th scope="col" class="py-2 pr-3 font-medium">Mois</th>
                        <th scope="col" class="py-2 px-3 font-medium text-right">Jours utilisés</th>
                        <template v-if="hasAnyDiscount">
                            <th scope="col" class="py-2 px-3 font-medium text-right">Brut</th>
                            <th scope="col" class="py-2 px-3 font-medium text-right">Réduction</th>
                            <th scope="col" class="py-2 px-3 font-medium text-right">Net</th>
                        </template>
                        <template v-else>
                            <th scope="col" class="py-2 px-3 font-medium text-right">Montant HT</th>
                        </template>
                        <th v-if="showInvoiceNumberColumn" scope="col" class="py-2 px-3 font-medium">N° annexe</th>
                        <th v-if="$slots['row-actions']" scope="col" class="py-2 pl-3 font-medium text-right">
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
                        <template v-if="hasAnyDiscount">
                            <!-- Colonne Brut -->
                            <td class="py-2 px-3 text-right font-mono tabular-nums whitespace-nowrap text-slate-500">
                                <template v-if="entry.hasMissingPricing">
                                    <span class="text-xs italic">·</span>
                                </template>
                                <template v-else-if="entry.daysUsed === 0">
                                    ·
                                </template>
                                <template v-else>
                                    {{ formatEur((entry.grossTotalCents ?? 0) / 100, 2) }}
                                </template>
                            </td>
                            <!-- Colonne Réduction -->
                            <td class="py-2 px-3 text-right font-mono tabular-nums whitespace-nowrap">
                                <template v-if="entry.hasMissingPricing || entry.daysUsed === 0 || (entry.totalDiscountCents ?? 0) === 0">
                                    <span class="text-slate-300">·</span>
                                </template>
                                <template v-else>
                                    <span class="text-emerald-700">
                                        -{{ formatEur((entry.totalDiscountCents ?? 0) / 100, 2) }}
                                    </span>
                                </template>
                            </td>
                            <!-- Colonne Net (final à payer) -->
                            <td class="py-2 px-3 text-right font-mono tabular-nums whitespace-nowrap font-medium">
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
                        </template>
                        <template v-else>
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
                        </template>
                        <td v-if="showInvoiceNumberColumn" class="py-2 px-3 whitespace-nowrap">
                            <template v-if="entry.existingInvoiceNumber">
                                <span class="inline-flex items-center gap-1.5">
                                    <Tooltip
                                        v-if="entryHasDivergence(entry)"
                                        max-width="22rem"
                                    >
                                        <AlertTriangle
                                            class="shrink-0 text-amber-500"
                                            :size="14"
                                            :stroke-width="2"
                                            aria-label="Données obsolètes"
                                        />
                                        <template #content>
                                            {{ divergenceTooltip(entry) }}
                                        </template>
                                    </Tooltip>
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

        <!-- Mobile < md : cards verticales par mois -->
        <ul class="flex flex-col gap-2 md:hidden">
            <li
                v-for="(entry, idx) in monthlyBilling.entries"
                :key="entry.month"
                :class="[
                    'rounded-xl border px-3 py-2.5 text-sm',
                    entry.hasMissingPricing
                        ? 'border-amber-200 bg-amber-50/40 text-amber-700'
                        : entry.daysUsed === 0
                            ? 'border-slate-200 bg-white text-slate-400'
                            : 'border-slate-200 bg-white text-slate-800',
                ]"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="font-medium">{{ MONTH_LABELS[idx] }}</span>
                    <span class="font-mono tabular-nums whitespace-nowrap">
                        <template v-if="entry.hasMissingPricing">
                            <span class="text-xs italic">Tarif manquant</span>
                        </template>
                        <template v-else-if="entry.daysUsed === 0">
                            ·
                        </template>
                        <template v-else>
                            {{ formatEur((entry.totalCents ?? 0) / 100, 2) }}
                        </template>
                    </span>
                </div>
                <!-- Sur mobile, le détail brut/réduction passe sous le total
                     net en métadonnée discrète (pas de réorganisation full
                     pour préserver la densité). Affiché uniquement si une
                     réduction a été appliquée au mois. -->
                <div
                    v-if="hasAnyDiscount && (entry.totalDiscountCents ?? 0) > 0"
                    class="mt-1.5 flex items-baseline justify-between gap-2 text-xs text-emerald-700"
                >
                    <span>Brut : <span class="font-mono tabular-nums text-slate-500">{{ formatEur((entry.grossTotalCents ?? 0) / 100, 2) }}</span></span>
                    <span class="font-mono tabular-nums">
                        -{{ formatEur((entry.totalDiscountCents ?? 0) / 100, 2) }}
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                    <span class="font-mono tabular-nums">{{ entry.daysUsed }} j</span>
                    <span v-if="showInvoiceNumberColumn && entry.existingInvoiceNumber" class="inline-flex items-center gap-1.5">
                        <Tooltip
                            v-if="entryHasDivergence(entry)"
                            max-width="22rem"
                        >
                            <AlertTriangle
                                class="shrink-0 text-amber-500"
                                :size="14"
                                :stroke-width="2"
                                aria-label="Données obsolètes"
                            />
                            <template #content>
                                {{ divergenceTooltip(entry) }}
                            </template>
                        </Tooltip>
                        <span class="font-mono text-xs text-slate-600">{{ entry.existingInvoiceNumber }}</span>
                    </span>
                </div>
                <div v-if="$slots['row-actions']" class="mt-2 flex justify-end">
                    <slot name="row-actions" :entry="entry" />
                </div>
            </li>
        </ul>

        <p
            v-if="monthlyBilling.hasAnyMissingPricing"
            class="mt-4 rounded-lg bg-amber-50/60 px-3 py-2 text-xs text-amber-800"
        >
            Un ou plusieurs mois ne peuvent pas être chiffrés faute de
            tarif annuel renseigné sur les véhicules concernés. Renseignez
            les tarifs depuis la fiche véhicule pour débloquer le calcul.
        </p>
    </component>
</template>

<script setup lang="ts">
/**
 * Section Facturation de la fiche Location (Phase 14.D V1.2 · extension).
 *
 * Affiche le coût de location **isolé** du contrat, mois civil par
 * mois civil. Si plusieurs contrats du même véhicule × entreprise
 * cohabitent sur un même mois, le total contrat-isolé peut différer
 * de la facture réelle (qui consolide les jours via combo optimal) ·
 * c'est documenté côté backend et signalé subtilement à l'utilisateur
 * dans le footer de la card.
 */
import { computed } from 'vue';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

const props = defineProps<{
    breakdown: App.Data.User.Billing.ContractBillingBreakdownData | null;
}>();

/**
 * Lot 3 réductions commerciales · vrai si au moins un mois du
 * breakdown contrat-isolé a appliqué une réduction. Pilote
 * l'affichage du total réduction sous le total HT.
 */
const hasAnyDiscount = computed<boolean>(
    () => (props.breakdown?.totalDiscountCentsPartial ?? 0) > 0,
);
</script>

<template>
    <Card v-if="breakdown">
        <template #header>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Facturation
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Coût de location de cette location, mois par mois.
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-medium tracking-wider uppercase text-slate-500">
                        Total HT
                    </p>
                    <p
                        class="font-mono text-base font-semibold tabular-nums"
                        :class="breakdown.totalCents === null
                            ? 'text-slate-400 italic'
                            : 'text-slate-900'"
                    >
                        <template v-if="breakdown.totalCents === null">
                            Indisponible
                        </template>
                        <template v-else>
                            {{ formatEur(breakdown.totalCents / 100, 2) }}
                        </template>
                    </p>
                    <p class="font-mono text-xs text-slate-500 tabular-nums">
                        {{ breakdown.totalDaysUsed }} jour{{ breakdown.totalDaysUsed > 1 ? 's' : '' }}
                    </p>
                    <!-- Lot 3 · si une réduction a été appliquée au
                         contrat sur au moins un mois, l'économie totale
                         apparaît sous le total HT en complément. -->
                    <p
                        v-if="hasAnyDiscount"
                        class="mt-1 font-mono text-xs text-emerald-700 tabular-nums whitespace-nowrap"
                    >
                        Réductions appliquées -{{ formatEur(breakdown.totalDiscountCentsPartial / 100, 2) }}
                    </p>
                </div>
            </div>
        </template>

        <table v-if="breakdown.months.length > 0" class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-medium tracking-wider uppercase text-slate-500">
                    <th class="py-2 pr-3 font-medium">Mois</th>
                    <th class="py-2 px-3 font-medium text-right">Jours</th>
                    <th class="py-2 pl-3 font-medium text-right">Coût HT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="row in breakdown.months"
                    :key="`${row.year}-${row.month}`"
                    :class="row.hasMissingPricing
                        ? 'bg-amber-50/40 text-amber-700'
                        : 'text-slate-800'"
                >
                    <td class="py-2 pr-3 text-sm">
                        {{ MONTH_LABELS[row.month - 1] }} {{ row.year }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono tabular-nums">
                        {{ row.daysInMonth }}
                    </td>
                    <td class="py-2 pl-3 text-right font-mono tabular-nums">
                        <template v-if="row.hasMissingPricing">
                            <span class="text-xs italic">Tarif {{ row.year }} manquant</span>
                        </template>
                        <template v-else>
                            <!-- Lot 3 · si une réduction a été appliquée
                                 sur ce mois, on expose brut barré + net
                                 + badge `RentalDiscountPill` discret. -->
                            <template v-if="(row.discountCents ?? 0) > 0">
                                <div class="flex items-center justify-end gap-2">
                                    <RentalDiscountPill
                                        :basis-points="Math.round(((row.discountCents ?? 0) / (row.grossTotalCents ?? 1)) * 10000)"
                                        :with-icon="false"
                                    />
                                    <div class="flex flex-col items-end">
                                        <span class="text-[11px] text-slate-400 line-through">
                                            {{ formatEur((row.grossTotalCents ?? 0) / 100, 2) }}
                                        </span>
                                        <span>
                                            {{ formatEur((row.totalCents ?? 0) / 100, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                {{ formatEur((row.totalCents ?? 0) / 100, 2) }}
                            </template>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <p
            v-if="breakdown.hasAnyMissingPricing"
            class="mt-4 rounded-lg bg-amber-50/60 px-3 py-2 text-xs text-amber-800"
        >
            Un ou plusieurs mois ne peuvent pas être chiffrés faute de
            tarif annuel renseigné sur le véhicule. Renseignez le tarif
            depuis la fiche véhicule pour débloquer le calcul.
        </p>

    </Card>
</template>

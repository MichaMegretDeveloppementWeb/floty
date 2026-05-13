<script setup lang="ts">
/**
 * Page Show Facture (Phase 14.F V1.2). Détail immuable + lien de
 * téléchargement du PDF généré à l'émission.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Download, FileText, History } from 'lucide-vue-next';
import { computed } from 'vue';
import InvoiceDivergenceBanner from '@/Components/Domain/Billing/InvoiceDivergenceBanner.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';
import { download as downloadRoute, show as invoicesShowRoute } from '@/routes/user/invoices';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

const props = defineProps<{
    invoice: App.Data.User.Invoice.InvoiceData;
}>();

const periodLabel = computed<string>(
    () => `${MONTH_LABELS[props.invoice.month - 1]} ${props.invoice.year}`,
);

const downloadUrl = computed<string>(() =>
    downloadRoute.url({ invoice: props.invoice.id }),
);
</script>

<template>
    <Head :title="`Facture ${props.invoice.invoiceNumber}`" />

    <UserLayout>
        <div class="flex flex-col gap-6 max-w-[60em] m-auto w-full">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <FileText :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="text-xs font-medium tracking-wider uppercase text-slate-500">
                            Facture {{ periodLabel }}
                        </p>
                        <h1 class="font-mono text-xl font-normal text-slate-900">
                            {{ invoice.invoiceNumber }}
                        </h1>
                    </div>
                </div>
                <a
                    :href="downloadUrl"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors duration-[120ms] hover:bg-slate-800"
                >
                    <Download :size="14" :stroke-width="1.75" />
                    Télécharger le PDF
                </a>
            </div>

            <!-- Bandeau « Cette facture a été remplacée » : si on consulte
                 une version obsolète. -->
            <div
                v-if="invoice.isObsolete && invoice.supersededByInvoiceId"
                class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
            >
                <History
                    :size="18"
                    :stroke-width="1.75"
                    class="mt-0.5 shrink-0 text-slate-500"
                />
                <div class="flex flex-1 flex-col gap-1 text-sm">
                    <p class="font-medium text-slate-900">
                        Version obsolète
                    </p>
                    <p class="text-slate-600">
                        Cette facture a été remplacée par
                        <Link
                            :href="invoicesShowRoute.url({ invoice: invoice.supersededByInvoiceId })"
                            class="inline-flex items-center gap-1 font-mono font-medium text-blue-600 hover:underline"
                        >
                            {{ invoice.supersededByInvoiceNumber }}
                            <ArrowRight :size="12" :stroke-width="1.75" />
                        </Link>
                        suite à une régénération. Son PDF reste consultable pour l'audit.
                    </p>
                </div>
            </div>

            <!-- Bandeau « Remplace la facture #YYY » : si cette facture
                 est elle-même la régénération d'une version antérieure. -->
            <div
                v-else-if="invoice.supersedesInvoiceId"
                class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
            >
                <History
                    :size="18"
                    :stroke-width="1.75"
                    class="mt-0.5 shrink-0 text-slate-500"
                />
                <p class="text-sm text-slate-600">
                    Remplace la facture
                    <Link
                        :href="invoicesShowRoute.url({ invoice: invoice.supersedesInvoiceId })"
                        class="font-mono font-medium text-blue-600 hover:underline"
                    >
                        {{ invoice.supersedesInvoiceNumber }}
                    </Link>
                    (régénérée à partir du périmètre contractuel actuel).
                </p>
            </div>

            <!-- Bandeau divergence (sous header + bouton PDF) -->
            <InvoiceDivergenceBanner
                v-if="invoice.divergence?.hasDivergence"
                :invoice-id="invoice.id"
                :invoice-number="invoice.invoiceNumber"
                :divergence="invoice.divergence"
            />

            <!-- Méta -->
            <Card>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                            Entreprise
                        </dt>
                        <dd class="mt-1 text-sm">
                            <Link
                                :href="companiesShowRoute.url({ company: invoice.companyId })"
                                class="font-medium text-slate-900 transition-colors duration-[120ms] hover:text-blue-600 hover:underline"
                            >
                                {{ invoice.companyShortCode }}
                                <span class="block text-xs text-slate-500">{{ invoice.companyLegalName }}</span>
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                            Émise le
                        </dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900">
                            {{ formatDateFr(invoice.generatedAt) }}
                            <span v-if="invoice.generatedByUserName" class="block text-xs text-slate-500">
                                par {{ invoice.generatedByUserName }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                            Total HT
                        </dt>
                        <dd class="mt-1 font-mono text-lg font-semibold text-slate-900 tabular-nums">
                            {{ formatEur(invoice.totalHtCents / 100, 2) }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <!-- Lignes véhicules -->
            <Card>
                <template #header>
                    <h2 class="text-base font-semibold text-slate-900">
                        Lignes facturées ({{ invoice.lines.length }})
                    </h2>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Lignes de la facture {{ invoice.invoiceNumber }}
                        </caption>
                        <thead>
                            <tr class="text-left text-xs font-medium tracking-wider uppercase text-slate-500">
                                <th scope="col" class="py-2 pr-3 font-medium">Véhicule</th>
                                <th scope="col" class="py-2 px-3 font-medium text-right">Jours</th>
                                <th scope="col" class="py-2 px-3 font-medium">Décomposition</th>
                                <th scope="col" class="py-2 pl-3 font-medium text-right">Total HT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="line in invoice.lines"
                                :key="line.id"
                                class="text-slate-800"
                            >
                                <td class="py-4 pr-3">
                                    <Link
                                        :href="vehiclesShowRoute.url({ vehicle: line.vehicleId })"
                                        class="font-medium text-slate-900 transition-colors duration-[120ms] hover:text-blue-600 hover:underline"
                                    >
                                        {{ line.vehicleLabelSnapshot }}
                                    </Link>
                                </td>
                                <td class="py-4 px-3 text-right font-mono tabular-nums">
                                    {{ line.daysUsed }}
                                </td>
                                <td class="py-4 px-3 text-xs text-slate-600">
                                    <template v-if="line.monthsBilled > 0">
                                        {{ line.monthsBilled }} mois × {{ formatEur(line.monthlyRateCents / 100, 2) }}
                                    </template>
                                    <template v-if="line.monthsBilled > 0 && (line.weeksBilled > 0 || line.daysBilled > 0)">
                                        +
                                    </template>
                                    <template v-if="line.weeksBilled > 0">
                                        {{ line.weeksBilled }} sem × {{ formatEur(line.weeklyRateCents / 100, 2) }}
                                    </template>
                                    <template v-if="line.weeksBilled > 0 && line.daysBilled > 0">
                                        +
                                    </template>
                                    <template v-if="line.daysBilled > 0">
                                        {{ line.daysBilled }} j × {{ formatEur(line.dailyRateCents / 100, 2) }}
                                    </template>
                                </td>
                                <td class="py-4 pl-3 text-right font-mono font-medium tabular-nums">
                                    {{ formatEur(line.totalHtCents / 100, 2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-1 border-slate-700">
                                <td class="pt-3 pr-4 font-semibold text-slate-900" colspan="3">
                                    Total HT
                                </td>
                                <td class="pt-3 pl-3 text-right font-mono font-semibold text-slate-900 tabular-nums">
                                    {{ formatEur(invoice.totalHtCents / 100, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>

            <!-- Empreinte d'intégrité -->
            <p class="text-xs text-slate-400">
                Empreinte SHA-256 du PDF :
                <span class="font-mono">{{ invoice.pdfHash.slice(0, 32) }}…</span>
            </p>
        </div>
    </UserLayout>
</template>

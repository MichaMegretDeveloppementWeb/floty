<script setup lang="ts">
/**
 * Page Show Facture (Phase 14.F V1.2). Détail immuable + lien de
 * téléchargement du PDF généré à l'émission.
 */
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import InvoiceDivergenceBanner from '@/Components/Domain/Billing/InvoiceDivergenceBanner.vue';
import InvoiceHistoryTimeline from '@/Components/Domain/Billing/InvoiceHistoryTimeline.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';
import { download as downloadRoute } from '@/routes/user/invoices';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

const props = defineProps<{
    invoice: App.Data.User.Invoice.InvoiceData;
    // Inertia::defer · arrive en 2e round-trip apres mount initial.
    // null pour les factures obsoletes (figees au moment de la regen).
    divergence?: App.Data.User.Invoice.InvoiceDivergenceData | null;
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
                        <div class="flex flex-wrap items-baseline gap-2">
                            <h1 class="font-mono text-xl font-normal text-slate-900">
                                {{ invoice.invoiceNumber }}
                            </h1>
                            <span
                                v-if="invoice.isObsolete"
                                class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700"
                                title="Cette version a été remplacée par une régénération. Son PDF reste consultable pour l'audit."
                            >
                                Version obsolète
                            </span>
                        </div>
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

            <!-- Bandeau divergence (sous header + bouton PDF) · uniquement
                 sur la version active. Servi en `Inertia::defer` apres
                 mount initial (audit perf 2026-05-16 P1.4) · skeleton
                 bref puis bandeau si applicable, ou rien si conforme. -->
            <Deferred v-if="!invoice.isObsolete" data="divergence">
                <template #fallback>
                    <div class="h-12 animate-pulse rounded-xl bg-slate-100" />
                </template>
                <InvoiceDivergenceBanner
                    v-if="divergence?.hasDivergence"
                    :invoice-id="invoice.id"
                    :invoice-number="invoice.invoiceNumber"
                    :divergence="divergence"
                />
            </Deferred>

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

            <!-- Timeline historique (visible dès qu'il existe ≥ 2 versions
                 pour le couple entreprise × année × mois) · permet de
                 naviguer entre versions actives et obsolètes. -->
            <Card v-if="invoice.historyChain.length > 1">
                <InvoiceHistoryTimeline
                    :entries="invoice.historyChain"
                    :current-invoice-id="invoice.id"
                />
            </Card>

            <!-- Empreinte d'intégrité -->
            <p class="text-xs text-slate-400">
                Empreinte SHA-256 du PDF :
                <span class="font-mono">{{ invoice.pdfHash.slice(0, 32) }}…</span>
            </p>
        </div>
    </UserLayout>
</template>

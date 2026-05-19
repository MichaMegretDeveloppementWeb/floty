<script setup lang="ts">
/**
 * Récap inline affiché 7s après une génération en masse d'annexes
 * (Bulk Generate Invoices). Auto-fade-out en fin de timer.
 *
 * Le rapport vit comme shared prop Inertia
 * (`usePage().props.bulkInvoiceReport`), alimentée par
 * `HandleInertiaRequests::resolveBulkInvoiceReport()` à partir de la
 * session flash posée par `InvoiceController::bulkGenerate()`. La prop
 * n'est présente que sur le render qui suit immédiatement le POST · null
 * sur les visites suivantes.
 *
 * Le toast (canal flash standard) communique le résumé court ; ce
 * composant communique le détail (mois générés avec numéros d'annexe,
 * mois en échec avec la raison typée).
 */
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { monthLabel } from '@/Utils/format/monthLabels';

type ReportShape = App.Data.User.Invoice.BulkInvoiceGenerationReportData;

const page = usePage();

function readReport(): ReportShape | null {
    // La shared prop est typée dans `Inertia.PageProps` côté global (cf.
    // `HandleInertiaRequests::share`) · cast local pour éviter une
    // déclaration de module qui pollue tout le projet pour un seul
    // canal.
    const value = (page.props as Record<string, unknown>).bulkInvoiceReport;
    if (value === null || value === undefined) {
        return null;
    }

    return value as ReportShape;
}

const visible = ref<boolean>(false);
const cachedReport = ref<ReportShape | null>(null);
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

const failureReasonLabel: Record<App.Enums.Invoice.BulkInvoiceGenerationFailureReason, string> = {
    missing_pricing: 'Tarif annuel manquant',
    already_exists: 'Annexe déjà émise (race)',
    not_past_month: 'Mois non écoulé',
    unexpected: 'Erreur inattendue',
};

const generatedLine = computed<string | null>(() => {
    const r = cachedReport.value;
    if (r === null || r.generated.length === 0) {
        return null;
    }

    const items = r.generated
        .map((g) => `${monthLabel(g.month)} (${g.invoiceNumber})`)
        .join(', ');

    return items;
});

function clearTimer(): void {
    if (dismissTimer !== null) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
}

function showReport(report: ReportShape): void {
    clearTimer();

    cachedReport.value = report;
    visible.value = true;

    dismissTimer = setTimeout(() => {
        visible.value = false;
        dismissTimer = null;
    }, 7000);
}

onMounted(() => {
    const initial = readReport();
    if (initial !== null) {
        showReport(initial);
    }
});

watch(
    () => (page.props as Record<string, unknown>).bulkInvoiceReport,
    () => {
        const next = readReport();
        if (next !== null) {
            showReport(next);
        }
    },
);

onUnmounted(() => {
    clearTimer();
});
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible && cachedReport"
            class="flex flex-col gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-700"
            role="status"
            aria-live="polite"
        >
            <div v-if="generatedLine" class="flex items-start gap-2">
                <CheckCircle2
                    class="mt-px shrink-0 text-emerald-600"
                    :size="14"
                    :stroke-width="1.75"
                    aria-hidden="true"
                />
                <p>
                    <span class="font-medium text-emerald-700">
                        {{ cachedReport.generated.length }} annexe<template v-if="cachedReport.generated.length > 1">s</template> générée<template v-if="cachedReport.generated.length > 1">s</template>
                    </span>
                    <span class="text-slate-600"> · {{ generatedLine }}</span>
                </p>
            </div>
            <div
                v-for="failed in cachedReport.failed"
                :key="failed.month"
                class="flex items-start gap-2"
            >
                <XCircle
                    class="mt-px shrink-0 text-rose-600"
                    :size="14"
                    :stroke-width="1.75"
                    aria-hidden="true"
                />
                <p>
                    <span class="font-medium text-rose-700">{{ monthLabel(failed.month) }}</span>
                    <span class="text-slate-600"> · {{ failureReasonLabel[failed.reason] }}</span>
                </p>
            </div>
        </div>
    </Transition>
</template>

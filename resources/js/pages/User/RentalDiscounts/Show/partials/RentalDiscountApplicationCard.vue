<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import { index as invoicesIndexRoute } from '@/routes/user/invoices';
import { formatDateFr } from '@/Utils/format/formatDateFr';

defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-base font-semibold text-slate-900">
                Application
            </h2>
        </template>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                    Période
                </dt>
                <dd class="mt-1 text-sm text-slate-900">
                    Du {{ formatDateFr(rentalDiscount.startDate) }}
                    <br>
                    au {{ formatDateFr(rentalDiscount.endDate) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                    Factures impactées
                </dt>
                <dd class="mt-1 flex items-center gap-2 text-sm text-slate-900">
                    <FileText :size="14" :stroke-width="1.75" class="text-slate-400" aria-hidden="true" />
                    <span class="font-mono font-medium tabular-nums">{{ rentalDiscount.invoiceLinesCount }}</span>
                    <span class="text-xs text-slate-500">
                        ligne{{ rentalDiscount.invoiceLinesCount > 1 ? 's' : '' }} de facture
                    </span>
                </dd>
            </div>
            <div v-if="rentalDiscount.createdByUserName">
                <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                    Créée par
                </dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ rentalDiscount.createdByUserName }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium tracking-wider uppercase text-slate-500">
                    Créée le
                </dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ formatDateFr(rentalDiscount.createdAt) }}
                </dd>
            </div>
        </dl>

        <div
            v-if="rentalDiscount.notes"
            class="mt-4 rounded-lg bg-slate-50 px-4 py-3"
        >
            <p class="text-xs font-medium tracking-wider uppercase text-slate-500">
                Notes internes
            </p>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">
                {{ rentalDiscount.notes }}
            </p>
        </div>

        <div
            v-if="rentalDiscount.invoiceLinesCount > 0"
            class="mt-4 rounded-lg border border-blue-100 bg-blue-50/40 px-4 py-3 text-xs text-blue-900"
        >
            Cette réduction figure sur {{ rentalDiscount.invoiceLinesCount }} ligne{{ rentalDiscount.invoiceLinesCount > 1 ? 's' : '' }} de facture déjà émise{{ rentalDiscount.invoiceLinesCount > 1 ? 's' : '' }}.
            <Link
                :href="invoicesIndexRoute.url({ query: { divergentOnly: 1, companyId: rentalDiscount.companyId } })"
                class="font-medium text-blue-700 hover:underline"
            >
                Voir les annexes de facture
            </Link>
        </div>
    </Card>
</template>

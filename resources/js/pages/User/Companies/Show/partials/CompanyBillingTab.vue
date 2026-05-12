<script setup lang="ts">
/**
 * Onglet « Facturation » de la fiche Company Show (Phase 14 V1.2).
 *
 * Affiche un récap 12 mois × {jours utilisés, montant HT facturable}
 * pour cette entreprise sur l'année sélectionnée + un bouton d'action
 * par mois : « Générer » si aucune facture n'est encore émise, ou
 * « Voir #YYYY-MM-NNNN » si une facture existe déjà (immuabilité ·
 * la regénération n'est pas autorisée). Le sélecteur d'année est
 * piloté par `?billingYear=` URL (mirroir du pattern fiscal / activité,
 * cf. `CompanyController::show`).
 */
import { router } from '@inertiajs/vue3';
import GenerateInvoiceButton from '@/Components/Domain/Billing/GenerateInvoiceButton.vue';
import MonthlyBillingBreakdownCard from '@/Components/Domain/Billing/MonthlyBillingBreakdownCard.vue';
import { show as companiesShowRoute } from '@/routes/user/companies';
import YearPills from "@/Components/Ui/YearPills/YearPills.vue";

const props = defineProps<{
    companyId: number;
    monthlyBilling: App.Data.User.Billing.MonthlyBillingBreakdownData;
    /**
     * Plage continue [firstYear..currentYear] partagée avec l'onglet
     * Contrats · cohérence UX : on ne propose que les années où
     * l'entreprise a un contrat plausible.
     */
    availableYears: readonly number[];
    activeYear: number;
}>();

function selectYear(year: number): void {
    if (year === props.activeYear) {
        return;
    }

    router.get(
        companiesShowRoute.url({ company: props.companyId }),
        { billingYear: year, tab: 'billing' },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['companyBilling', 'billingYear'],
            replace: true,
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <YearPills
            v-if="availableYears.length > 0"
            :years="availableYears"
            :active-year="activeYear"
            @select="selectYear"
        />

        <MonthlyBillingBreakdownCard
            :monthly-billing="monthlyBilling"
            title="Facturation mensuelle"
            description="Cumul des locations facturables à cette entreprise, mois par mois, sur l'année sélectionnée."
        >
            <template #row-actions="{ entry }">
                <GenerateInvoiceButton
                    :company-id="companyId"
                    :year="monthlyBilling.year"
                    :month="entry.month"
                    :days-used="entry.daysUsed"
                    :has-missing-pricing="entry.hasMissingPricing"
                    :existing-invoice-id="entry.existingInvoiceId"
                    :existing-invoice-number="entry.existingInvoiceNumber"
                    :existing-invoice-total-cents="entry.invoicedTotalCents"
                    :existing-invoiced-days-used="entry.invoicedDaysUsed"
                    :current-total-cents="entry.totalCents"
                />
            </template>
        </MonthlyBillingBreakdownCard>
    </div>
</template>

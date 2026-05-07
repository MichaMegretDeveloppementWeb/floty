<script setup lang="ts">
/**
 * Vue Entreprise — stub minimal (chantier P1, fondations backend).
 *
 * Sera remplacé en chantier P2 par la vraie page : sélecteur entreprise
 * en haut + heatmap company-scoped (cellules `weeksForCompany`, couleur
 * `weeksGlobal`) + lien depuis Company Show.
 */
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';

defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapCompanyVehicleData[];
    company: App.Data.User.Company.CompanyOptionData;
    companies: App.Data.User.Company.CompanyOptionData[];
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
}>();
</script>

<template>
    <Head :title="`Planning · ${company.shortCode}`" />

    <UserLayout>
        <div class="flex flex-col gap-4 p-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                Vue Entreprise — {{ company.shortCode }}
            </h1>
            <p class="text-sm text-slate-500">
                Stub P1. La page complète arrive en chantier P2.
            </p>
            <p class="text-xs text-slate-400">
                {{ vehicles.length }} véhicules · {{ companies.length }} entreprises · année {{ selectedYear }}
            </p>
        </div>
    </UserLayout>
</template>

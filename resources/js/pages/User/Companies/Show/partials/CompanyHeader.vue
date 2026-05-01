<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { index as indexRoute } from '@/routes/user/companies';

defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <Link :href="indexRoute().url" class="text-sm text-slate-500 hover:underline">
            ← Entreprises
        </Link>
        <div class="flex items-center justify-between">
            <CompanyTag
                :name="company.legalName"
                :initials="company.shortCode.slice(0, 2)"
                :color="company.color"
            />
            <div class="text-right text-sm text-slate-600">
                <div>{{ company.contractsCount }} contrat(s)</div>
                <div>{{ company.activeDriversCount }} / {{ company.totalDriversCount }} conducteur(s) actif(s)</div>
            </div>
        </div>
    </div>
</template>

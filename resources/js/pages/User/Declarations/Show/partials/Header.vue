<script setup lang="ts">
import { Building2 } from 'lucide-vue-next';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { badgeForDeclaration } from '@/Utils/format/declarationStatus';

defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
}>();
</script>

<template>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <Building2 :size="22" :stroke-width="1.75" />
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                    Déclaration fiscale
                </p>
                <h1 class="text-2xl font-semibold text-slate-900">
                    {{ declaration.companyShortCode }} · {{ declaration.fiscalYear }}
                </h1>
                <p class="text-sm text-slate-500">{{ declaration.companyLegalName }}</p>
                <p
                    v-if="declaration.reference"
                    class="mt-1 inline-flex w-fit items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-700"
                >
                    {{ declaration.reference }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <StatusPill :tone="badgeForDeclaration(declaration.status, declaration.isObsolete).tone">
                {{ badgeForDeclaration(declaration.status, declaration.isObsolete).label }}
            </StatusPill>
        </div>
    </header>
</template>

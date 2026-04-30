<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

const props = defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();

const titleText = props.contract.contractReference ?? `Contrat #${props.contract.id}`;
</script>

<template>
    <header class="flex flex-col gap-3">
        <Link
            :href="contractsIndexRoute.url()"
            class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
        >
            <ArrowLeft :size="14" :stroke-width="1.75" />
            Retour aux contrats
        </Link>
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
            >
                {{ titleText }}
            </h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <Badge :tone="contractTypeBadgeTone[contract.contractType]">
                    {{ contractTypeShortLabel[contract.contractType] }}
                </Badge>
                <span class="font-mono text-slate-600">
                    {{ formatDateFr(contract.startDate) }}
                    <span class="text-slate-400">→</span>
                    {{ formatDateFr(contract.endDate) }}
                </span>
                <span class="text-slate-400">·</span>
                <span class="text-slate-600">
                    {{ contract.durationDays }}
                    jour{{ contract.durationDays > 1 ? 's' : '' }}
                </span>
            </div>
        </div>
    </header>
</template>

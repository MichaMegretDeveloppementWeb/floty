<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

defineProps<{
    contract: App.Data.User.Contract.ContractData;
}>();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6"
    >
        <h2 class="text-base font-semibold text-slate-900">Informations</h2>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Date de début
                </dt>
                <dd class="mt-1 font-mono text-sm text-slate-900">
                    {{ formatDateFr(contract.startDate) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Date de fin
                </dt>
                <dd class="mt-1 font-mono text-sm text-slate-900">
                    {{ formatDateFr(contract.endDate) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Durée
                </dt>
                <dd class="mt-1 font-mono text-sm text-slate-900">
                    {{ contract.durationDays }}
                    jour{{ contract.durationDays > 1 ? 's' : '' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Type
                </dt>
                <dd class="mt-1">
                    <Badge :tone="contractTypeBadgeTone[contract.contractType]">
                        {{ contractTypeShortLabel[contract.contractType] }}
                    </Badge>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Référence contrat
                </dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ contract.contractReference ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase text-slate-500">
                    Conducteur
                </dt>
                <dd class="mt-1 text-sm text-slate-900">
                    {{ contract.driverFullName ?? '—' }}
                </dd>
            </div>
        </dl>
        <div>
            <dt class="text-xs font-medium uppercase text-slate-500">
                Notes
            </dt>
            <dd
                class="mt-1 text-sm whitespace-pre-line"
                :class="contract.notes ? 'text-slate-700' : 'text-slate-900'"
            >
                {{ contract.notes ?? '—' }}
            </dd>
        </div>
    </section>
</template>

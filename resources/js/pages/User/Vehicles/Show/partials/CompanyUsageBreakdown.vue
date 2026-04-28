<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

type CompanyColor = App.Enums.Company.CompanyColor;

const colorBgClass: Record<CompanyColor, string> = {
    indigo: 'bg-company-indigo',
    emerald: 'bg-company-emerald',
    amber: 'bg-company-amber',
    rose: 'bg-company-rose',
    violet: 'bg-company-violet',
    teal: 'bg-company-teal',
    orange: 'bg-company-orange',
    cyan: 'bg-company-cyan',
};

const maxDays = computed<number>(() => {
    if (props.stats.companies.length === 0) {
        return 0;
    }

    return Math.max(...props.stats.companies.map((c) => c.daysUsed));
});

const widthFor = (days: number): string => {
    if (maxDays.value === 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((days / maxDays.value) * 100))}%`;
};

const totalDays = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.daysUsed, 0),
);

const totalTax = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.taxDue, 0),
);
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Répartition par entreprise utilisatrice
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Année {{ props.stats.fiscalYear }} —
                    {{ props.stats.companies.length }} entreprise{{
                        props.stats.companies.length > 1 ? 's' : ''
                    }}
                </p>
            </div>
        </template>

        <p
            v-if="props.stats.companies.length === 0"
            class="text-sm text-slate-500 italic"
        >
            Pas encore d'utilisation enregistrée cette année.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="company in props.stats.companies"
                :key="company.companyId"
                class="flex flex-col gap-1.5"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            :class="[
                                'inline-block h-2.5 w-2.5 shrink-0 rounded-full',
                                colorBgClass[company.color],
                            ]"
                            aria-hidden="true"
                        />
                        <span
                            class="font-mono text-xs font-semibold tracking-wider text-slate-700 uppercase"
                        >
                            {{ company.shortCode }}
                        </span>
                        <span
                            class="truncate text-sm text-slate-600"
                            :title="company.legalName"
                        >
                            {{ company.legalName }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-slate-500">
                            {{ company.daysUsed }} j
                        </span>
                        <span class="font-semibold text-slate-900">
                            {{ formatEur(company.taxDue) }}
                        </span>
                    </div>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div
                        :class="[
                            'h-full rounded-full',
                            colorBgClass[company.color],
                        ]"
                        :style="{ width: widthFor(company.daysUsed) }"
                    />
                </div>
            </li>
        </ul>

        <template
            v-if="props.stats.companies.length > 0"
            #footer
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
            >
                <span class="text-slate-500">
                    Total : {{ totalDays }} jour{{ totalDays > 1 ? 's' : '' }}
                </span>
                <span class="font-semibold text-slate-900">
                    {{ formatEur(totalTax) }}
                </span>
            </div>
        </template>
    </Card>
</template>

<script setup lang="ts">
/**
 * Hero d'identité de la fiche entreprise (chantier K, ADR-0020 D3).
 *
 * Avatar coloré (CompanyTag style) + raison sociale en titre fort +
 * shortCode mono + statuts inline (Active / OIG / Indiv.) + actions
 * rapides à droite. Pas de KPIs ici — ils vivent dans la section
 * « Aperçu par année » du corps de la fiche.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { index as indexRoute } from '@/routes/user/companies';

type Company = App.Data.User.Company.CompanyDetailData;
type CompanyColor = App.Enums.Company.CompanyColor;

const props = defineProps<{
    company: Company;
}>();

const avatarBgClass = computed<string>(() => {
    const map: Record<CompanyColor, string> = {
        indigo: 'bg-company-indigo',
        emerald: 'bg-company-emerald',
        amber: 'bg-company-amber',
        rose: 'bg-company-rose',
        violet: 'bg-company-violet',
        teal: 'bg-company-teal',
        orange: 'bg-company-orange',
        cyan: 'bg-company-cyan',
    };

    return map[props.company.color];
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <Link
            :href="indexRoute().url"
            class="inline-flex items-center gap-1 self-start text-sm text-slate-500 hover:text-slate-700"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            Entreprises
        </Link>

        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <span
                    :class="[
                        avatarBgClass,
                        'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-semibold uppercase tracking-tight text-white',
                    ]"
                    aria-hidden="true"
                >
                    {{ company.shortCode }}
                </span>

                <div class="flex flex-col gap-1">
                    <h1 class="text-xl font-semibold text-slate-900">
                        {{ company.legalName }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600">
                        <span class="font-mono uppercase tracking-tight text-slate-500">
                            {{ company.shortCode }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    company.isActive ? 'bg-emerald-500' : 'bg-slate-300',
                                ]"
                                aria-hidden="true"
                            />
                            {{ company.isActive ? 'Active' : 'Inactive' }}
                        </span>
                        <span v-if="company.isOig" class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">
                            OIG
                        </span>
                        <span v-if="company.isIndividualBusiness" class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-medium text-blue-700">
                            Entreprise individuelle
                        </span>
                        <span v-if="company.siren" class="font-mono text-slate-400">
                            SIREN {{ company.siren }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 gap-2">
                <Button variant="ghost" size="sm" disabled>
                    Modifier
                </Button>
            </div>
        </div>
    </div>
</template>

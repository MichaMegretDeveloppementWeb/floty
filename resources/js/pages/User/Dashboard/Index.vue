<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Building2,
    CalendarCheck,
    CalendarDays,
    Car,
    Receipt,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { index as assignmentsIndexRoute } from '@/routes/user/assignments';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import { index as fiscalRulesIndexRoute } from '@/routes/user/fiscal-rules';
import { index as planningIndexRoute } from '@/routes/user/planning';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    stats: App.Data.User.Dashboard.DashboardStatsData;
}>();

const { currentYear: fiscalYear } = useFiscalYear();

type QuickLink = {
    label: string;
    description: string;
    href: string;
    icon: LucideIcon;
    featured?: boolean;
};

const quickLinks: QuickLink[] = [
    {
        label: "Vue d'ensemble",
        description:
            "Heatmap annuelle des 52 semaines — la vue maîtresse pour attribuer et visualiser l'impact fiscal en temps réel.",
        href: planningIndexRoute.url(),
        icon: CalendarDays,
        featured: true,
    },
    {
        label: 'Attribution rapide',
        description:
            'Sélectionner un véhicule, une entreprise et plusieurs dates en une passe.',
        href: assignmentsIndexRoute.url(),
        icon: CalendarCheck,
    },
    {
        label: 'Flotte',
        description:
            'Véhicules enregistrés, caractéristiques fiscales et taxes annuelles.',
        href: vehiclesIndexRoute.url(),
        icon: Car,
    },
    {
        label: 'Entreprises',
        description:
            'Clients utilisateurs de la flotte, jours cumulés et taxes par entreprise.',
        href: companiesIndexRoute.url(),
        icon: Building2,
    },
    {
        label: 'Règles de calcul',
        description:
            'Comprendre comment Floty calcule les taxes CO₂ et polluants — barèmes, exonérations, cadre.',
        href: fiscalRulesIndexRoute.url(),
        icon: Receipt,
    },
];
</script>

<template>
    <Head title="Tableau de bord" />

    <UserLayout>
        <div class="flex flex-col gap-8">
            <header>
                <p class="eyebrow mb-2">Tableau de bord · {{ fiscalYear }}</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Aperçu flotte {{ fiscalYear }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Vue synthétique de l'activité en cours.
                </p>
            </header>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                    label="Véhicules en flotte"
                    :value="String(stats.vehiclesCount)"
                    caption="Véhicules non sortis"
                />
                <KpiCard
                    label="Entreprises actives"
                    :value="String(stats.companiesCount)"
                    caption="Clients utilisateurs de la flotte"
                />
                <KpiCard
                    :label="`Jours-véhicule ${fiscalYear}`"
                    :value="stats.assignmentsYear.toLocaleString('fr-FR')"
                    caption="Total cumulé sur l'année sélectionnée"
                />
                <KpiCard
                    :label="`Taxes dues ${fiscalYear}`"
                    :value="formatEur(stats.totalTaxDue)"
                    caption="CO₂ + polluants, toutes entreprises"
                />
            </div>

            <!-- Accès rapide -->
            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900">
                    Accès rapide
                </h2>
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="link in quickLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'group relative flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 transition-colors duration-[120ms] ease-out hover:border-slate-400',
                            link.featured ? 'sm:col-span-2 lg:col-span-2' : '',
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-slate-900">
                                <component
                                    :is="link.icon"
                                    :size="18"
                                    :stroke-width="1.75"
                                    class="text-slate-700"
                                    aria-hidden="true"
                                />
                                <p
                                    class="text-base font-semibold tracking-tight"
                                >
                                    {{ link.label }}
                                </p>
                            </div>
                            <ArrowUpRight
                                :size="16"
                                :stroke-width="1.75"
                                class="text-slate-300 transition-colors duration-[120ms] ease-out group-hover:text-slate-700"
                                aria-hidden="true"
                            />
                        </div>
                        <p class="text-sm text-slate-600">
                            {{ link.description }}
                        </p>
                    </Link>
                </div>
            </section>
        </div>
    </UserLayout>
</template>

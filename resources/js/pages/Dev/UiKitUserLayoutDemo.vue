<script setup lang="ts">
import AlertRow from '@/Components/Ui/AlertRow/AlertRow.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import type { DataTableColumn } from '@/types/ui';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    FileClock,
    Plus,
    TrendingUp,
} from 'lucide-vue-next';

type VehicleRow = {
    id: number;
    plate: string;
    model: string;
    type: 'VP' | 'VU';
    co2: number;
    occupancy: number;
};

const rows: readonly VehicleRow[] = [
    {
        id: 1,
        plate: 'EH-142-AZ',
        model: 'Peugeot 308',
        type: 'VP',
        co2: 112,
        occupancy: 62,
    },
    {
        id: 2,
        plate: 'EL-887-KB',
        model: 'Renault Clio',
        type: 'VP',
        co2: 95,
        occupancy: 44,
    },
    {
        id: 3,
        plate: 'FA-221-MX',
        model: 'Citroën Berlingo',
        type: 'VU',
        co2: 138,
        occupancy: 81,
    },
    {
        id: 4,
        plate: 'EN-554-PQ',
        model: 'Tesla Model 3',
        type: 'VP',
        co2: 0,
        occupancy: 28,
    },
];

const columns: readonly DataTableColumn<VehicleRow>[] = [
    { key: 'plate', label: 'Immatriculation', mono: true },
    { key: 'model', label: 'Modèle' },
    { key: 'type', label: 'Type' },
    { key: 'co2', label: 'CO₂', align: 'right', mono: true },
    { key: 'occupancy', label: 'Occup. 2026', align: 'right' },
];
</script>

<template>
    <Head title="UI Kit — UserLayout" />

    <UserLayout>
        <template #default="{ year }">
            <div class="flex flex-col gap-6">
                <header>
                    <p class="eyebrow mb-2">Tableau de bord · {{ year }}</p>
                    <h1
                        class="text-3xl font-semibold tracking-tight text-slate-900"
                    >
                        Aperçu flotte {{ year }}
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Démo du layout authentifié Floty — sidebar fixe,
                        topbar collante avec recherche, sélecteur d'année et
                        menu utilisateur.
                    </p>
                </header>

                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                >
                    <KpiCard
                        label="Taux d'occupation moyen"
                        value="68"
                        suffix="%"
                        trend="+4,2 %"
                        trend-direction="up"
                        caption="Semaine en cours · moyenne des 100 véhicules"
                    />
                    <KpiCard
                        label="Véhicules actifs"
                        value="97"
                        suffix="/ 100"
                        caption="3 immobilisés pour maintenance"
                    />
                    <KpiCard
                        label="Entreprises utilisatrices"
                        value="30"
                        caption="8 actives cette semaine"
                    />
                    <KpiCard
                        :label="`Taxes estimées ${year}`"
                        value="142 840 €"
                        trend="+8,1 %"
                        trend-direction="up"
                        caption="vs année précédente"
                    />
                </div>

                <section
                    class="rounded-xl border border-slate-200 bg-white"
                >
                    <header
                        class="flex items-center justify-between border-b border-slate-200 px-6 py-4"
                    >
                        <div>
                            <p class="eyebrow mb-1">Section</p>
                            <h2
                                class="text-xl font-semibold text-slate-900"
                            >
                                Flotte récente
                            </h2>
                        </div>
                        <Button>
                            <template #icon-left>
                                <Plus :size="14" :stroke-width="1.75" />
                            </template>
                            Ajouter un véhicule
                        </Button>
                    </header>
                    <DataTable
                        :columns="columns"
                        :rows="rows"
                        :row-key="(row) => row.id"
                    >
                        <template #cell-plate="{ value }">
                            <Plate :value="String(value)" />
                        </template>
                        <template #cell-type="{ value }">
                            <Badge
                                :tone="value === 'VU' ? 'amber' : 'slate'"
                            >
                                {{ value }}
                            </Badge>
                        </template>
                        <template #cell-co2="{ value }">
                            {{ value }} g/km
                        </template>
                        <template #cell-occupancy="{ value }">
                            <div
                                class="flex items-center justify-end gap-2"
                            >
                                <div
                                    class="h-[5px] w-[72px] overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full bg-slate-900"
                                        :style="{
                                            width: `${Number(value)}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-9 text-right font-mono text-xs text-slate-600"
                                >
                                    {{ value }} %
                                </span>
                            </div>
                        </template>
                    </DataTable>
                </section>

                <section
                    class="rounded-xl border border-slate-200 bg-white px-2 py-3"
                >
                    <p class="eyebrow mb-2 px-4">Signaux à traiter</p>
                    <div class="flex flex-col divide-y divide-slate-100">
                        <AlertRow
                            tone="warning"
                            title="3 véhicules sous-utilisés"
                            description="Dacia Duster, Skoda Octavia, Hyundai Kona"
                        >
                            <template #icon>
                                <AlertTriangle
                                    :size="15"
                                    :stroke-width="1.75"
                                />
                            </template>
                        </AlertRow>
                        <AlertRow
                            tone="info"
                            title="Meridian Construct en forte demande"
                            description="7/7 jours sur ses 2 véhicules habituels"
                        >
                            <template #icon>
                                <TrendingUp
                                    :size="15"
                                    :stroke-width="1.75"
                                />
                            </template>
                        </AlertRow>
                        <AlertRow
                            tone="danger"
                            title="Déclarations 2025 — 4 en attente"
                        >
                            <template #icon>
                                <FileClock
                                    :size="15"
                                    :stroke-width="1.75"
                                />
                            </template>
                        </AlertRow>
                        <AlertRow
                            tone="success"
                            title="Tesla Model 3 — exonération confirmée"
                        >
                            <template #icon>
                                <CheckCircle2
                                    :size="15"
                                    :stroke-width="1.75"
                                />
                            </template>
                        </AlertRow>
                    </div>
                </section>
            </div>
        </template>
    </UserLayout>
</template>

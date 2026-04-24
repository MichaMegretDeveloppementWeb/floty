<script setup lang="ts">
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import FlotyWordmark from '@/Components/Brand/FlotyWordmark.vue';
import AlertRow from '@/Components/Ui/AlertRow/AlertRow.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import Drawer from '@/Components/Ui/Drawer/Drawer.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Kbd from '@/Components/Ui/Kbd/Kbd.vue';
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import Toast from '@/Components/Ui/Toast/Toast.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useToasts } from '@/composables/useToasts';
import type { DataTableColumn } from '@/types/ui';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Car,
    Clock,
    Download,
    FileClock,
    Filter,
    MoreHorizontal,
    Pencil,
    Plus,
    Send,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { ref } from 'vue';

const toasts = useToasts();

const modalOpen = ref<boolean>(false);
const drawerOpen = ref<boolean>(false);
const confirmOpen = ref<boolean>(false);
const confirmLoading = ref<boolean>(false);

type VehicleRow = {
    id: number;
    plate: string;
    model: string;
    type: 'VP' | 'VU';
    co2: number;
    occupancy: number;
};

const vehicleRows: readonly VehicleRow[] = [
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

const vehicleColumns: readonly DataTableColumn<VehicleRow>[] = [
    { key: 'plate', label: 'Immatriculation', mono: true },
    { key: 'model', label: 'Modèle' },
    { key: 'type', label: 'Type' },
    { key: 'co2', label: 'CO₂', align: 'right', mono: true },
    { key: 'occupancy', label: 'Occup. 2026', align: 'right' },
];

const handleConfirm = (): void => {
    confirmLoading.value = true;
    setTimeout(() => {
        confirmLoading.value = false;
        confirmOpen.value = false;
        toasts.push({
            tone: 'success',
            title: 'Véhicule supprimé',
            description: 'Peugeot 308 · EH-142-AZ retiré de la flotte.',
        });
    }, 800);
};

const demoCompanyName = ref<string>('Dacia Duster');
const demoPlate = ref<string>('EH-142-AZ');
const demoInvalidSiren = ref<string>('123');
const demoCo2 = ref<number | null>(142);
const demoCompany = ref<string>('acme');
const demoDate = ref<string>('2026-04-21');
const demoTransferOk = ref<boolean>(true);
const demoNewsletter = ref<boolean>(false);
const demoSearch = ref<string>('');

const companyOptions = [
    { value: 'acme', label: 'ACME Industries' },
    { value: 'nordwell', label: 'Nordwell' },
    { value: 'bastion', label: 'Bastion Transport' },
    { value: 'helios', label: 'Helios Flotte' },
];

type Swatch = { label: string; class: string };

const slateScale: Swatch[] = [
    { label: '50', class: 'bg-slate-50' },
    { label: '100', class: 'bg-slate-100' },
    { label: '200', class: 'bg-slate-200' },
    { label: '300', class: 'bg-slate-300' },
    { label: '400', class: 'bg-slate-400' },
    { label: '500', class: 'bg-slate-500' },
    { label: '600', class: 'bg-slate-600' },
    { label: '700', class: 'bg-slate-700' },
    { label: '800', class: 'bg-slate-800' },
    { label: '900', class: 'bg-slate-900' },
];

const blueScale: Swatch[] = [
    { label: '50', class: 'bg-blue-50' },
    { label: '100', class: 'bg-blue-100' },
    { label: '200', class: 'bg-blue-200' },
    { label: '300', class: 'bg-blue-300' },
    { label: '400', class: 'bg-blue-400' },
    { label: '500', class: 'bg-blue-500' },
    { label: '600', class: 'bg-blue-600' },
    { label: '700', class: 'bg-blue-700' },
    { label: '800', class: 'bg-blue-800' },
    { label: '900', class: 'bg-blue-900' },
];

const densityScale: Swatch[] = [
    { label: '0', class: 'bg-density-0' },
    { label: '1', class: 'bg-density-1' },
    { label: '2', class: 'bg-density-2' },
    { label: '3', class: 'bg-density-3' },
    { label: '4', class: 'bg-density-4' },
    { label: '5', class: 'bg-density-5' },
    { label: '6', class: 'bg-density-6' },
    { label: '7', class: 'bg-density-7' },
];

const companyChips: Swatch[] = [
    { label: 'indigo', class: 'bg-company-indigo' },
    { label: 'emerald', class: 'bg-company-emerald' },
    { label: 'amber', class: 'bg-company-amber' },
    { label: 'rose', class: 'bg-company-rose' },
    { label: 'violet', class: 'bg-company-violet' },
    { label: 'teal', class: 'bg-company-teal' },
    { label: 'orange', class: 'bg-company-orange' },
    { label: 'cyan', class: 'bg-company-cyan' },
];
</script>

<template>
    <Head title="UI Kit — Showcase" />

    <div class="min-h-screen bg-slate-50 py-6 md:py-12">
        <div class="mx-auto max-w-[1400px] px-4 md:px-8">
            <header class="mb-8 md:mb-12">
                <p class="eyebrow mb-3">Design system · Floty</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-4xl"
                >
                    UI Kit — Showcase
                </h1>
                <p class="mt-2 max-w-2xl text-base text-slate-600">
                    Page de validation visuelle des composants du design
                    system. Accessible uniquement en environnement local. Chaque
                    composant produit est ajouté ici au fur et à mesure pour
                    validation avant d'être utilisé dans l'application.
                </p>
            </header>

            <section class="mb-10">
                <p class="eyebrow mb-4">Identité de marque</p>
                <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                    <div
                        class="grid grid-cols-1 gap-6 md:grid-cols-3 md:gap-x-8"
                    >
                        <div class="flex flex-col gap-3">
                            <p class="eyebrow">Mark</p>
                            <div
                                class="flex h-24 items-center justify-center rounded-lg bg-slate-50 text-slate-900"
                            >
                                <FlotyMark :size="48" />
                            </div>
                            <p class="font-mono text-[10px] text-slate-500">
                                32 × 32 — 3 pastilles + anneau
                            </p>
                        </div>
                        <div class="flex flex-col gap-3">
                            <p class="eyebrow">Wordmark</p>
                            <div
                                class="flex h-24 items-center justify-center rounded-lg bg-slate-50 text-slate-900"
                            >
                                <FlotyWordmark :height="36" />
                            </div>
                            <p class="font-mono text-[10px] text-slate-500">
                                Mark + « Floty » DM Sans 600
                            </p>
                        </div>
                        <div class="flex flex-col gap-3">
                            <p class="eyebrow">Mark on dark</p>
                            <div
                                class="flex h-24 items-center justify-center rounded-lg bg-slate-900 text-white"
                            >
                                <FlotyMark :size="48" />
                            </div>
                            <p class="font-mono text-[10px] text-slate-500">
                                `currentColor` adapte au contexte
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mb-10">
                <p class="eyebrow mb-4">Fondations</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-3">Typographie</p>
                        <div class="space-y-2">
                            <p class="text-4xl font-semibold text-slate-900">
                                Aa — DM Sans
                            </p>
                            <p class="font-mono text-xl text-slate-700">
                                EH-142-AZ · 452 367 891
                            </p>
                            <p class="text-base text-slate-600">
                                Le renard roux dérobe la voiture partagée.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-3">Palette — slate</p>
                        <div class="flex gap-1">
                            <div
                                v-for="swatch in slateScale"
                                :key="swatch.label"
                                :class="[
                                    'h-8 flex-1 rounded-sm ring-1 ring-slate-200/60',
                                    swatch.class,
                                ]"
                                :title="`slate-${swatch.label}`"
                            />
                        </div>
                        <p class="eyebrow mt-4 mb-3">Palette — blue</p>
                        <div class="flex gap-1">
                            <div
                                v-for="swatch in blueScale"
                                :key="swatch.label"
                                :class="[
                                    'h-8 flex-1 rounded-sm ring-1 ring-slate-200/60',
                                    swatch.class,
                                ]"
                                :title="`blue-${swatch.label}`"
                            />
                        </div>
                        <p class="eyebrow mt-4 mb-3">Heatmap — densité</p>
                        <div class="flex gap-1">
                            <div
                                v-for="swatch in densityScale"
                                :key="swatch.label"
                                :class="[
                                    'h-8 flex-1 rounded-[3px] ring-1 ring-slate-200',
                                    swatch.class,
                                ]"
                                :title="`density-${swatch.label}`"
                            />
                        </div>
                        <p class="eyebrow mt-4 mb-3">Chips entreprise</p>
                        <div class="flex gap-2">
                            <div
                                v-for="swatch in companyChips"
                                :key="swatch.label"
                                :class="['h-8 w-8 rounded-full', swatch.class]"
                                :title="`company-${swatch.label}`"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section id="atoms" class="mb-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="eyebrow mb-1">Section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            Atomes
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Button, Badge, StatusPill, CompanyTag, Kbd, Plate,
                            InputError, Eyebrow.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        En cours
                    </span>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Button — Variants</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                Primary
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button>Nouvelle attribution</Button>
                                <Button>
                                    <template #icon-left>
                                        <Plus :size="14" :stroke-width="1.75" />
                                    </template>
                                    Ajouter un véhicule
                                </Button>
                                <Button size="sm">Continuer</Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Secondary
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button variant="secondary">Exporter</Button>
                                <Button variant="secondary">
                                    <template #icon-left>
                                        <Download
                                            :size="14"
                                            :stroke-width="1.75"
                                        />
                                    </template>
                                    Exporter CSV
                                </Button>
                                <Button variant="secondary">
                                    <template #icon-left>
                                        <Filter
                                            :size="14"
                                            :stroke-width="1.75"
                                        />
                                    </template>
                                    Filtrer
                                </Button>
                                <Button variant="secondary" size="sm">
                                    Annuler
                                </Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Ghost
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button variant="ghost">Retour</Button>
                                <Button variant="ghost" size="sm">
                                    Tout voir
                                </Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Destructive
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button variant="destructive-soft">
                                    Supprimer
                                </Button>
                                <Button variant="destructive">
                                    Confirmer la suppression
                                </Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Disabled
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button disabled>Valider</Button>
                                <Button variant="secondary" disabled>
                                    Exporter
                                </Button>
                                <Button variant="ghost" disabled>Retour</Button>
                                <Button size="sm" disabled>Continuer</Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Loading
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button loading>Enregistrer</Button>
                                <Button variant="secondary" loading>
                                    Patienter
                                </Button>
                                <Button variant="destructive" loading>
                                    Supprimer
                                </Button>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Icon only
                            </p>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    aria-label="Plus d'actions"
                                >
                                    <MoreHorizontal
                                        :size="14"
                                        :stroke-width="1.75"
                                    />
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    aria-label="Modifier"
                                >
                                    <Pencil :size="14" :stroke-width="1.75" />
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    aria-label="Supprimer"
                                >
                                    <Trash2 :size="14" :stroke-width="1.75" />
                                </Button>
                                <span
                                    class="font-mono text-[10px] text-slate-400"
                                >
                                    30 × 30 · aria-label obligatoire
                                </span>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Block
                            </p>
                            <div class="max-w-sm">
                                <Button block>Continuer</Button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Badge — Type véhicule</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                Par tonalité
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge tone="slate">VP</Badge>
                                <Badge tone="amber">VU</Badge>
                                <Badge tone="blue">CI</Badge>
                                <Badge tone="emerald">BB</Badge>
                                <Badge tone="rose">WLTP</Badge>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">StatusPill — Déclaration</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                Cycle déclaration
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusPill tone="amber">
                                    <template #icon>
                                        <Clock
                                            :size="12"
                                            :stroke-width="1.75"
                                        />
                                    </template>
                                    Brouillon
                                </StatusPill>
                                <StatusPill tone="blue">Prête</StatusPill>
                                <StatusPill tone="emerald">
                                    <template #icon>
                                        <Send
                                            :size="12"
                                            :stroke-width="1.75"
                                        />
                                    </template>
                                    Envoyée
                                </StatusPill>
                                <StatusPill tone="slate">Désactivée</StatusPill>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Mise en avant
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusPill tone="emerald">
                                    <template #icon>
                                        <CheckCircle2
                                            :size="12"
                                            :stroke-width="1.75"
                                        />
                                    </template>
                                    Recommandé
                                </StatusPill>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">CompanyTag</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                Huit couleurs
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <CompanyTag
                                    color="indigo"
                                    name="ACME Industries"
                                    initials="AC"
                                />
                                <CompanyTag
                                    color="emerald"
                                    name="Nordwell"
                                    initials="NW"
                                />
                                <CompanyTag
                                    color="amber"
                                    name="Bastion Transport"
                                    initials="BT"
                                />
                                <CompanyTag
                                    color="rose"
                                    name="Helios Flotte"
                                    initials="HF"
                                />
                                <CompanyTag
                                    color="violet"
                                    name="Vallée Services"
                                    initials="VS"
                                />
                                <CompanyTag
                                    color="teal"
                                    name="Trident Loc"
                                    initials="TL"
                                />
                                <CompanyTag
                                    color="orange"
                                    name="Cedar & Co"
                                    initials="CC"
                                />
                                <CompanyTag
                                    color="cyan"
                                    name="Atlas Fleet"
                                    initials="AF"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Plate & Kbd</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                Immatriculation
                            </p>
                            <div class="flex flex-wrap items-center gap-3">
                                <Plate value="EH-142-AZ" />
                                <Plate value="av-274-zk" />
                                <span class="text-sm text-slate-400">
                                    (uppercase automatique)
                                </span>
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Raccourcis clavier
                            </p>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Kbd>⌘</Kbd>
                                <Kbd>K</Kbd>
                                <span
                                    class="mx-2 text-xs text-slate-400 select-none"
                                >
                                    ·
                                </span>
                                <Kbd>Ctrl</Kbd>
                                <span class="text-xs text-slate-400">+</span>
                                <Kbd>Entrée</Kbd>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">InputError & Eyebrow</p>
                        <div
                            class="grid grid-cols-[140px_1fr] items-center gap-x-6 gap-y-4"
                        >
                            <p class="font-mono text-[10px] text-slate-500">
                                InputError
                            </p>
                            <div class="max-w-sm">
                                <input
                                    type="text"
                                    value="123"
                                    aria-describedby="demo-siren-error"
                                    class="w-full rounded-lg border border-rose-600 bg-white px-3 py-2 text-base text-rose-700 shadow-[0_0_0_3px_var(--color-rose-50)] focus:outline-none"
                                />
                                <InputError
                                    id="demo-siren-error"
                                    message="SIREN invalide — 9 chiffres attendus."
                                />
                            </div>

                            <p class="font-mono text-[10px] text-slate-500">
                                Eyebrow
                            </p>
                            <div>
                                <p class="eyebrow mb-1">Vue d'ensemble</p>
                                <h3
                                    class="text-xl font-semibold text-slate-900"
                                >
                                    Flotte 2026
                                </h3>
                                <p class="text-sm text-slate-600">
                                    Classe CSS <code class="font-mono">
                                        .eyebrow
                                    </code>
                                    — pas de composant dédié.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="inputs" class="mb-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="eyebrow mb-1">Section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            Inputs
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            TextInput, NumberInput, SelectInput, CheckboxInput,
                            DateInput, SearchInput.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        En cours
                    </span>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">TextInput</p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-6"
                        >
                            <TextInput
                                v-model="demoCompanyName"
                                label="Nom du véhicule"
                                placeholder="Dacia Duster, Peugeot Partner…"
                                required
                            />
                            <TextInput
                                v-model="demoPlate"
                                label="Immatriculation"
                                mono
                                hint="Format français, ex. EH-142-AZ."
                                required
                            />
                            <TextInput
                                v-model="demoInvalidSiren"
                                label="SIREN"
                                hint="9 chiffres."
                                error="SIREN invalide — 9 chiffres attendus."
                                mono
                                required
                            />
                            <TextInput
                                v-model="demoCompanyName"
                                label="Référence interne"
                                placeholder="Laissé vide pour auto-générer"
                                disabled
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">NumberInput</p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-x-6"
                        >
                            <NumberInput
                                v-model="demoCo2"
                                label="Émissions CO₂"
                                :step="1"
                                :min="0"
                            >
                                <template #unit>g/km</template>
                            </NumberInput>
                            <NumberInput
                                v-model="demoCo2"
                                label="Taux d'utilisation"
                                :step="0.1"
                                :min="0"
                                :max="100"
                            >
                                <template #unit>%</template>
                            </NumberInput>
                            <NumberInput
                                v-model="demoCo2"
                                label="Prix d'achat"
                                hint="HT, arrondi au centime."
                                :step="0.01"
                                :min="0"
                            >
                                <template #unit>€</template>
                            </NumberInput>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">SelectInput & DateInput</p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-6"
                        >
                            <SelectInput
                                v-model="demoCompany"
                                label="Entreprise utilisatrice"
                                :options="companyOptions"
                                required
                            />
                            <DateInput
                                v-model="demoDate"
                                label="Date de début"
                                required
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">CheckboxInput</p>
                        <div class="flex flex-col gap-4">
                            <CheckboxInput
                                v-model="demoTransferOk"
                                label="Véhicule partageable entre entreprises"
                                hint="Décocher pour réserver ce véhicule à une seule entreprise."
                            />
                            <CheckboxInput
                                v-model="demoNewsletter"
                                label="Recevoir les notifications fiscales par e-mail"
                            />
                            <CheckboxInput
                                v-model="demoTransferOk"
                                label="Option désactivée"
                                hint="Nécessite un rôle administrateur."
                                disabled
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">SearchInput</p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-6"
                        >
                            <SearchInput
                                v-model="demoSearch"
                                placeholder="Rechercher véhicule, entreprise…"
                                aria-label="Recherche globale"
                                :shortcut="['⌘', 'K']"
                            />
                            <SearchInput
                                v-model="demoSearch"
                                placeholder="Filtrer cette liste…"
                                aria-label="Filtrer la liste"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section id="molecules" class="mb-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="eyebrow mb-1">Section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            Molécules
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Card, KpiCard, Toast, AlertRow.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        En cours
                    </span>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">KpiCard</p>
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
                                label="Taxes CO₂ + polluants estimées 2026"
                                value="142 840 €"
                                trend="+8,1 %"
                                trend-direction="up"
                                caption="vs 2025 consolidé sur 30 déclarations"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Card — variantes</p>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-x-6"
                        >
                            <Card>
                                <p class="eyebrow mb-1">Par défaut</p>
                                <p class="text-base text-slate-700">
                                    Card sans en-tête ni pied. Padding
                                    <code class="font-mono">md</code>.
                                </p>
                            </Card>

                            <Card interactive>
                                <template #header>
                                    <p
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        Avec en-tête
                                    </p>
                                </template>
                                <p class="text-sm text-slate-600">
                                    Survolez pour voir la
                                    <code class="font-mono">shadow-sm</code>
                                    apparaître.
                                </p>
                            </Card>

                            <Card>
                                <template #header>
                                    <div class="flex items-center justify-between">
                                        <p
                                            class="text-base font-semibold text-slate-900"
                                        >
                                            En-tête + pied
                                        </p>
                                        <Badge tone="blue">V1</Badge>
                                    </div>
                                </template>
                                <p class="text-sm text-slate-600">
                                    Contenu principal du card.
                                </p>
                                <template #footer>
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="sm">
                                            Annuler
                                        </Button>
                                        <Button size="sm">Valider</Button>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">AlertRow</p>
                        <div class="flex flex-col divide-y divide-slate-100">
                            <AlertRow
                                tone="warning"
                                title="3 véhicules sous-utilisés (< 15 % d'occupation)"
                                description="Dacia Duster, Skoda Octavia, Hyundai Kona — envisager une réaffectation"
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
                                description="7/7 jours sur ses 2 véhicules habituels depuis 3 semaines"
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
                                title="Déclarations fiscales 2025 — 4 en attente"
                                description="ACME Industries, Cedar & Co, Delta Maintenance, Alpha Services"
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
                                title="Tesla Model 3 — exonération CO₂ 2026 confirmée"
                                description="Économie estimée : 420 € sur cette année fiscale"
                            >
                                <template #icon>
                                    <CheckCircle2
                                        :size="15"
                                        :stroke-width="1.75"
                                    />
                                </template>
                            </AlertRow>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Toast</p>
                        <div
                            class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-x-4"
                        >
                            <Toast
                                tone="success"
                                title="Déclaration envoyée"
                                description="ACME Industries · année 2025 transmise au fisc."
                            />
                            <Toast
                                tone="error"
                                title="Échec de l'envoi"
                                description="Le service des impôts est injoignable. Réessayez dans quelques minutes."
                            />
                            <Toast
                                tone="warning"
                                title="Véhicule sans classification CO₂"
                                description="3 véhicules nécessitent une mise à jour WLTP."
                            />
                            <Toast
                                tone="info"
                                title="Nouvelle période fiscale ouverte"
                                description="Vous pouvez préparer la déclaration 2026."
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section id="organisms" class="mb-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="eyebrow mb-1">Section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            Organismes
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Modal, ConfirmModal, Drawer, DataTable,
                            ToastContainer, EmptyState.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        En cours
                    </span>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">EmptyState</p>
                        <EmptyState
                            title="Aucun véhicule enregistré"
                            description="Ajoutez votre premier véhicule pour commencer à calculer les taxes CO₂ et polluants."
                        >
                            <template #icon>
                                <Car :size="20" :stroke-width="1.75" />
                            </template>
                            <template #actions>
                                <Button>
                                    <template #icon-left>
                                        <Plus :size="14" :stroke-width="1.75" />
                                    </template>
                                    Ajouter un véhicule
                                </Button>
                                <Button variant="secondary">
                                    Importer un CSV
                                </Button>
                            </template>
                        </EmptyState>
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">DataTable</p>
                        <DataTable
                            :columns="vehicleColumns"
                            :rows="vehicleRows"
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
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">Modal, Drawer & ConfirmModal</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button @click="modalOpen = true">
                                Ouvrir un modal
                            </Button>
                            <Button
                                variant="secondary"
                                @click="drawerOpen = true"
                            >
                                Ouvrir un drawer
                            </Button>
                            <Button
                                variant="destructive-soft"
                                @click="confirmOpen = true"
                            >
                                Confirmer une suppression
                            </Button>
                        </div>

                        <Modal
                            v-model:open="modalOpen"
                            title="Nouvelle attribution"
                            description="Attribuer un véhicule à une entreprise pour la période choisie."
                        >
                            <div class="flex flex-col gap-4">
                                <SelectInput
                                    v-model="demoCompany"
                                    label="Entreprise"
                                    :options="companyOptions"
                                    required
                                />
                                <div class="grid grid-cols-2 gap-4">
                                    <DateInput
                                        v-model="demoDate"
                                        label="Début"
                                        required
                                    />
                                    <DateInput
                                        v-model="demoDate"
                                        label="Fin"
                                    />
                                </div>
                            </div>
                            <template #footer>
                                <Button
                                    variant="ghost"
                                    @click="modalOpen = false"
                                >
                                    Annuler
                                </Button>
                                <Button
                                    data-autofocus
                                    @click="modalOpen = false"
                                >
                                    Créer l'attribution
                                </Button>
                            </template>
                        </Modal>

                        <Drawer
                            v-model:open="drawerOpen"
                            title="Peugeot 308"
                            description="EH-142-AZ · ajouté le 14 avril 2026"
                        >
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap gap-2">
                                    <Badge tone="slate">VP</Badge>
                                    <StatusPill tone="emerald">
                                        En service
                                    </StatusPill>
                                </div>
                                <TextInput
                                    v-model="demoCompanyName"
                                    label="Modèle"
                                />
                                <NumberInput
                                    v-model="demoCo2"
                                    label="Émissions CO₂"
                                    :step="1"
                                >
                                    <template #unit>g/km</template>
                                </NumberInput>
                                <CheckboxInput
                                    v-model="demoTransferOk"
                                    label="Partageable entre entreprises"
                                />
                            </div>
                            <template #footer>
                                <Button
                                    variant="ghost"
                                    @click="drawerOpen = false"
                                >
                                    Fermer
                                </Button>
                                <Button data-autofocus>
                                    Enregistrer
                                </Button>
                            </template>
                        </Drawer>

                        <ConfirmModal
                            v-model:open="confirmOpen"
                            tone="danger"
                            title="Supprimer ce véhicule ?"
                            message="Peugeot 308 (EH-142-AZ) sera retiré de la flotte. Les attributions passées restent conservées pour les déclarations fiscales."
                            confirm-label="Supprimer"
                            :loading="confirmLoading"
                            @confirm="handleConfirm"
                        />
                    </div>

                    <div class="rounded-xl bg-white p-6 ring-1 ring-slate-200">
                        <p class="eyebrow mb-4">
                            ToastContainer — déclencheurs
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="secondary"
                                @click="
                                    toasts.push({
                                        tone: 'success',
                                        title: 'Déclaration envoyée',
                                        description:
                                            'ACME Industries · 2025 transmise.',
                                    })
                                "
                            >
                                Success
                            </Button>
                            <Button
                                variant="secondary"
                                @click="
                                    toasts.push({
                                        tone: 'error',
                                        title: 'Échec de l\'envoi',
                                        description:
                                            'Service injoignable, réessayez.',
                                    })
                                "
                            >
                                Error
                            </Button>
                            <Button
                                variant="secondary"
                                @click="
                                    toasts.push({
                                        tone: 'warning',
                                        title: 'Classification CO₂ manquante',
                                        description: '3 véhicules à compléter.',
                                    })
                                "
                            >
                                Warning
                            </Button>
                            <Button
                                variant="secondary"
                                @click="
                                    toasts.push({
                                        tone: 'info',
                                        title: 'Période 2026 ouverte',
                                    })
                                "
                            >
                                Info
                            </Button>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">
                            Les toasts s'auto-suppriment après 5 s — clic sur
                            <code class="font-mono">×</code> pour fermer
                            immédiatement.
                        </p>
                    </div>
                </div>
            </section>

            <section id="layouts" class="mb-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="eyebrow mb-1">Section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            Layout
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            UserLayout — zone connectée avec Sidebar, TopBar,
                            YearSelector et UserMenu. Le layout prend tout le
                            viewport, il a donc sa page démo dédiée. Les pages
                            publiques seront traitées à part, hors UI Kit.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
                    >
                        En cours
                    </span>
                </div>

                <a
                    href="/dev/ui-kit/layout-user"
                    class="group flex max-w-xl flex-col gap-3 rounded-xl border border-slate-200 bg-white p-6 transition-shadow duration-[120ms] ease-out hover:shadow-sm"
                >
                    <p class="eyebrow text-slate-500">UserLayout</p>
                    <p class="text-xl font-semibold text-slate-900">
                        Zone connectée
                    </p>
                    <p class="text-sm text-slate-600">
                        Sidebar 240 px fixe avec navigation hiérarchisée
                        (Vue d'ensemble / Planning / Données / Fiscalité),
                        TopBar 64 px collante avec recherche globale,
                        sélecteur d'année fiscale et menu utilisateur.
                    </p>
                    <span
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-slate-900 group-hover:underline"
                    >
                        Voir la démo →
                    </span>
                </a>
            </section>
        </div>

        <ToastContainer />
    </div>
</template>

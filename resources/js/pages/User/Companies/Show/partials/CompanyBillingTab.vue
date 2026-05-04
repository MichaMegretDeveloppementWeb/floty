<script setup lang="ts">
/**
 * Onglet « Facturation » de la fiche Company Show — placeholder
 * explicite V1.2 (chantier N.3).
 *
 * V1.0 (MVP, en cours) : démo focus sur le calcul fiscal CO₂ +
 * polluants par entreprise utilisatrice. La facturation des loyers
 * (jours utilisés × tarifs véhicule) est volontairement reportée
 * en V1.2 post-MVP — décision tracée dans la roadmap projet.
 *
 * Cet onglet documente la portée prévue plutôt que d'afficher un
 * compteur trompeur ou un message vague. L'utilisateur sait
 * exactement ce qui arrive et quand.
 */
import { ArrowRight, FileText, History, Receipt, Wallet } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';

const features = [
    {
        icon: Wallet,
        title: 'Tarifs par véhicule',
        description:
            'Définir un tarif jour, semaine et mois par véhicule, par exercice fiscal. Versionnés dans le temps comme les caractéristiques fiscales.',
    },
    {
        icon: Receipt,
        title: 'Calcul mensuel automatique',
        description:
            'Pour chaque entreprise utilisatrice, agrégation mensuelle des jours utilisés × tarif applicable, ventilée par véhicule.',
    },
    {
        icon: FileText,
        title: 'Génération PDF',
        description:
            'Modèle de facture personnalisable, génération en 1 clic depuis ce tableau mensuel. Historique des factures émises consultable.',
    },
    {
        icon: History,
        title: 'Historique non modifiable',
        description:
            'Les factures émises sont figées (PDF + snapshot des tarifs au moment de l\'émission). Pas de modification rétroactive — conformité comptable.',
    },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Header placeholder avec badge V1.2 -->
        <Card>
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                    <Receipt :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-slate-900">
                            Facturation
                        </h3>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-blue-700 uppercase"
                        >
                            V1.2 — post-MVP
                        </span>
                    </div>
                    <p class="text-sm text-slate-600">
                        Le module de facturation des loyers (jours utilisés ×
                        tarifs véhicule) sera livré dans la version 1.2,
                        après la sortie du MVP. La fiscalité (CO₂ +
                        polluants) reste pleinement disponible dès la V1.0
                        dans l'onglet
                        <span class="font-medium text-slate-700"
                            >Fiscalité</span
                        >.
                    </p>
                </div>
            </div>
        </Card>

        <!-- Périmètre prévu -->
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <p
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Périmètre prévu
                    </p>
                    <p class="text-sm text-slate-500">
                        4 fonctionnalités cœur du module Facturation V1.2.
                    </p>
                </div>

                <ul class="grid gap-3 sm:grid-cols-2">
                    <li
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="feature.icon"
                                :size="16"
                                :stroke-width="1.75"
                                class="shrink-0 text-slate-500"
                            />
                            <h4
                                class="text-sm font-semibold text-slate-900"
                            >
                                {{ feature.title }}
                            </h4>
                        </div>
                        <p class="text-xs text-slate-600">
                            {{ feature.description }}
                        </p>
                    </li>
                </ul>
            </div>
        </Card>

        <!-- Pointer pédagogique vers Fiscalité -->
        <Card>
            <div
                class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-medium text-slate-900">
                        En attendant la V1.2
                    </p>
                    <p class="text-xs text-slate-500">
                        Consultez le détail fiscal de cette entreprise dans
                        l'onglet Fiscalité — répartition par véhicule + total
                        CO₂ et polluants par exercice.
                    </p>
                </div>
                <a
                    href="?tab=fiscal"
                    class="inline-flex items-center gap-1.5 self-start rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50 sm:self-auto"
                >
                    Voir Fiscalité
                    <ArrowRight :size="12" :stroke-width="1.75" />
                </a>
            </div>
        </Card>
    </div>
</template>

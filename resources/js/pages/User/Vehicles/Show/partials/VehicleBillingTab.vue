<script setup lang="ts">
/**
 * Onglet Facturation de la fiche véhicule (chantier η Phase 2 onglets) —
 * placeholder V1.2 (le module facturation arrivera dans une roadmap
 * post-MVP ; cf. `roadmap_v12_facturation`). L'onglet est exposé dès
 * maintenant pour stabiliser la nav, mais le contenu reste vide.
 */
import Card from '@/Components/Ui/Card/Card.vue';
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-base font-semibold text-slate-900">
                Facturation
            </h2>
        </template>

        <div class="py-12 text-center">
            <p class="text-sm text-slate-500">
                Module de facturation — à venir en V1.2.
            </p>
            <p class="mt-2 text-xs text-slate-400">
                Tarifs jour/semaine/mois par véhicule, calcul mensuel par
                entreprise, génération PDF custom, historique sans
                modification manuelle.
            </p>
        </div>
    </Card>
</template>

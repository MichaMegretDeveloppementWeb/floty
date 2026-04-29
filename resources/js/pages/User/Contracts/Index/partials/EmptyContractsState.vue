<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, Plus } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import { create as contractsCreateRoute } from '@/routes/user/contracts';
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center"
    >
        <FileText :size="32" class="text-slate-400" :stroke-width="1.5" />
        <div>
            <p class="text-base font-semibold text-slate-700">
                Aucun contrat enregistré
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Créez un contrat pour attribuer un véhicule à une
                entreprise utilisatrice sur une plage de dates.
            </p>
        </div>
        <Link :href="contractsCreateRoute.url()">
            <Button>
                <template #icon-left>
                    <Plus :size="14" :stroke-width="1.75" />
                </template>
                Nouveau contrat
            </Button>
        </Link>
    </div>
</template>

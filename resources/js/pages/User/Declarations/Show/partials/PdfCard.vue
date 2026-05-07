<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileText, RefreshCcw } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { download as downloadRoute, regenerate as regenerateRoute } from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
}>();

function handleRegenerate(): void {
    router.post(
        regenerateRoute.url({ declaration: props.declaration.id }),
        {},
        { preserveScroll: true },
    );
}

function handleDownload(): void {
    window.location.href = downloadRoute.url({ declaration: props.declaration.id });
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center gap-2">
                <FileText :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">PDF annexe documentaire</h2>
            </div>
        </template>

        <div v-if="declaration.generatedAt" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-sm text-slate-600">
                    Document généré le
                    <span class="font-medium text-slate-900">{{ formatDateFr(declaration.generatedAt) }}</span>.
                </p>
                <p v-if="declaration.generatedPdfHash" class="font-mono text-[11px] text-slate-400">
                    Hash : {{ declaration.generatedPdfHash.slice(0, 16) }}…
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button @click="handleDownload">
                    <Download :size="16" :stroke-width="1.75" />
                    Télécharger le PDF
                </Button>

                <Button v-if="declaration.isObsolete" variant="destructive-soft" @click="handleRegenerate">
                    <RefreshCcw :size="16" :stroke-width="1.75" />
                    Régénérer
                </Button>
            </div>
        </div>

        <div v-else class="flex flex-col gap-2 text-sm text-slate-500">
            <p>
                Aucun PDF n'a encore été produit pour cette déclaration. Une
                fois la revue achevée, la génération produira un document
                immuable consultable et téléchargeable ici.
            </p>
        </div>
    </Card>
</template>

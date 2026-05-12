<script setup lang="ts">
/**
 * Modale de détail du calcul fiscal **standalone** d'une attribution
 * (chantier UX-Loc). Ouverte depuis `ContractRecapCard` · affiche le
 * breakdown CO₂ + polluants, les exonérations appliquées et le total.
 *
 * Sémantique : LCD/LLD se calcule contrat par contrat individuellement
 * (pas de cumul annuel) · ce qui compte est ce que **ce contrat
 * précisément** coûte fiscalement.
 */
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    preview: App.Data.User.Fiscal.FiscalPreviewData | null;
    companyShortCode: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Modal
        v-model:open="open"
        title="Détail du calcul fiscal"
        size="md"
        @close="emit('close')"
    >
        <div v-if="preview === null" class="text-sm text-slate-500">
            Aucun calcul disponible.
        </div>

        <div v-else class="flex flex-col gap-5">
            <!-- Récap de l'attribution -->
            <section class="flex flex-col gap-2">
                <p class="eyebrow">
                    Attribution {{ companyShortCode }} · {{ preview.fiscalYear }}
                </p>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Jours retenus</span>
                    <span class="font-mono text-slate-900">{{ preview.daysCount }} j</span>
                </div>
            </section>

            <!-- Exonérations appliquées -->
            <section
                v-if="preview.breakdown.appliedExemptions.length > 0"
                class="flex flex-col gap-2"
            >
                <p class="eyebrow">Exonérations appliquées</p>
                <ul class="flex flex-col gap-1.5">
                    <li
                        v-for="exemption in preview.breakdown.appliedExemptions"
                        :key="exemption.ruleCode"
                        class="flex items-start justify-between gap-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                    >
                        <span>✓ {{ exemption.reason }}</span>
                        <span class="font-mono text-xs text-emerald-700">
                            {{ exemption.ruleCode }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Décomposition CO₂ + polluants + total -->
            <section class="flex flex-col gap-2 rounded-lg border border-blue-200 bg-blue-50/40 p-4">
                <p class="eyebrow text-blue-700">Décomposition</p>
                <div class="flex flex-col gap-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">
                            Taxe CO₂ ({{ preview.breakdown.co2Method }})
                        </span>
                        <span class="font-mono">{{ formatEur(preview.breakdown.co2Due, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Taxe polluants</span>
                        <span class="font-mono">{{ formatEur(preview.breakdown.pollutantsDue, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-blue-200 pt-2 text-base font-medium">
                        <span class="text-slate-900">Total</span>
                        <span class="font-mono font-semibold text-slate-900">
                            {{ formatEur(preview.breakdown.totalDue, 2) }}
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </Modal>
</template>

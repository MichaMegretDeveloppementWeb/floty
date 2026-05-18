<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [id: number];
}>();

const open = ref<boolean>(true);

function onConfirm(): void {
    emit('confirm', props.rentalDiscount.id);
}
</script>

<template>
    <Modal v-model:open="open" title="Supprimer la réduction commerciale" @close="emit('close')">
        <div class="flex flex-col gap-4">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                    <Trash2 :size="20" :stroke-width="1.75" />
                </span>
                <div class="flex flex-col gap-2">
                    <p class="text-sm text-slate-900">
                        Souhaitez-vous archiver la réduction
                        <span class="font-medium">
                            {{ rentalDiscount.label ?? 'sans libellé' }}
                        </span> ?
                    </p>
                    <p v-if="rentalDiscount.invoiceLinesCount > 0" class="text-xs text-slate-600">
                        Cette réduction figure sur {{ rentalDiscount.invoiceLinesCount }} ligne{{ rentalDiscount.invoiceLinesCount > 1 ? 's' : '' }} de facture déjà émise{{ rentalDiscount.invoiceLinesCount > 1 ? 's' : '' }}. Elles resteront inchangées (snapshot immuable). Seules les périodes futures ne bénéficieront plus de la réduction.
                    </p>
                    <p v-else class="text-xs text-slate-600">
                        L'archivage est réversible (soft-delete). Aucune facture n'est concernée par cette réduction.
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2">
                <Button variant="ghost" @click="emit('close')">
                    Annuler
                </Button>
                <Button variant="destructive" @click="onConfirm">
                    Archiver la réduction
                </Button>
            </div>
        </div>
    </Modal>
</template>

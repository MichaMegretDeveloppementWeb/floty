<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { computed } from 'vue';

type ConfirmTone = 'default' | 'danger';

const props = withDefaults(
    defineProps<{
        title: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
        tone?: ConfirmTone;
        loading?: boolean;
    }>(),
    {
        confirmLabel: 'Confirmer',
        cancelLabel: 'Annuler',
        tone: 'default',
        loading: false,
    },
);

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

const confirmVariant = computed(() =>
    props.tone === 'danger' ? 'destructive' : 'primary',
);

const handleCancel = (): void => {
    open.value = false;
    emit('cancel');
};

const handleConfirm = (): void => {
    emit('confirm');
};
</script>

<template>
    <Modal
        v-model:open="open"
        size="sm"
        :title="title"
        :close-on-backdrop="!loading"
        @close="handleCancel"
    >
        <p class="text-base text-slate-700">{{ message }}</p>
        <template #footer>
            <Button
                variant="ghost"
                :disabled="loading"
                @click="handleCancel"
            >
                {{ cancelLabel }}
            </Button>
            <Button
                :variant="confirmVariant"
                :loading="loading"
                data-autofocus
                @click="handleConfirm"
            >
                {{ confirmLabel }}
            </Button>
        </template>
    </Modal>
</template>

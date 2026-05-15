<script setup lang="ts">
import { Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /** MIME types acceptés (ex. 'application/pdf'). */
        accept: string;
        /** Taille max en bytes par fichier. */
        maxSizeBytes: number;
        /** Nombre max de fichiers acceptés en une fois. Par défaut illimité. */
        maxFiles?: number;
        disabled?: boolean;
        /** Permettre la sélection multiple via input (default: true). */
        multiple?: boolean;
    }>(),
    {
        maxFiles: undefined,
        disabled: false,
        multiple: true,
    },
);

const emit = defineEmits<{
    'files-added': [files: File[]];
    /**
     * F-41-011 (Lot 7 D12) · le composant Ui n'importe plus
     * `useToasts` directement (couche UI ne doit pas connaître les
     * concerns globaux). Le parent décide quoi faire des erreurs
     * (toast, inline message, log, etc.).
     */
    'rejected': [message: string];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const isDragOver = ref<boolean>(false);

const acceptedMimes = computed<string[]>(() =>
    props.accept
        .split(',')
        .map((m) => m.trim())
        .filter((m) => m.length > 0),
);

const MIME_LABEL_MAP: Record<string, string> = {
    'application/pdf': 'PDF',
    'image/jpeg': 'JPG',
    'image/png': 'PNG',
    'image/webp': 'WebP',
    'image/gif': 'GIF',
};

const acceptLabel = computed<string>(() =>
    acceptedMimes.value
        .map((mime) => MIME_LABEL_MAP[mime] ?? mime)
        .join(', '),
);

const maxSizeLabel = computed<string>(() => {
    const mb = props.maxSizeBytes / (1024 * 1024);

    return `${mb.toFixed(0)} Mo`;
});

function openFileDialog(): void {
    if (props.disabled) {
        return;
    }

    inputRef.value?.click();
}

function onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.files === null) {
        return;
    }

    handleFiles(Array.from(input.files));
    input.value = ''; // permet de re-sélectionner les mêmes fichiers
}

function onDragOver(event: DragEvent): void {
    if (props.disabled) {
        return;
    }

    event.preventDefault();
    isDragOver.value = true;
}

function onDragLeave(): void {
    isDragOver.value = false;
}

function onDrop(event: DragEvent): void {
    isDragOver.value = false;

    if (props.disabled || event.dataTransfer === null) {
        return;
    }

    event.preventDefault();
    handleFiles(Array.from(event.dataTransfer.files));
}

// La validation MIME ci-dessous est une UX guard, pas une garantie
// sécurité · `file.type` peut être arbitraire (DataTransfer arbitraire,
// extension renommée). La vraie validation se fait côté backend via
// `#[Mimes]` Spatie qui sniffe la signature du fichier.
function handleFiles(files: File[]): void {
    const valid: File[] = [];
    const errors: string[] = [];

    for (const file of files) {
        if (!acceptedMimes.value.includes(file.type)) {
            errors.push(`« ${file.name} » n'est pas au format ${acceptLabel.value}.`);
            continue;
        }

        if (file.size > props.maxSizeBytes) {
            errors.push(`« ${file.name} » dépasse ${maxSizeLabel.value}.`);
            continue;
        }

        valid.push(file);
    }

    if (props.maxFiles !== undefined && valid.length > props.maxFiles) {
        errors.push(
            `Vous ne pouvez pas ajouter plus de ${props.maxFiles} fichier${props.maxFiles > 1 ? 's' : ''} à la fois.`,
        );
        valid.splice(props.maxFiles);
    }

    for (const message of errors) {
        emit('rejected', message);
    }

    if (valid.length > 0) {
        emit('files-added', valid);
    }
}
</script>

<template>
    <div
        :class="[
            'flex flex-col items-center justify-center gap-2 rounded-lg w-full max-w-[35em] mx-auto mt-5 border-2 border-dashed p-8 text-center transition-colors duration-120 ease-out',
            disabled
                ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400'
                : isDragOver
                  ? 'cursor-pointer border-indigo-400 bg-indigo-50 text-indigo-700'
                  : 'cursor-pointer border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:bg-slate-50',
        ]"
        role="button"
        :aria-disabled="disabled"
        :tabindex="disabled ? -1 : 0"
        @click="openFileDialog"
        @keydown.enter.prevent="openFileDialog"
        @keydown.space.prevent="openFileDialog"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <Upload :size="32" :stroke-width="1.5" aria-hidden="true" />
        <p class="text-sm font-medium">
            <span v-if="isDragOver">Déposez vos fichiers ici</span>
            <span v-else>
                Glissez vos {{ acceptLabel }} ici
                <span class="text-slate-400"> ou </span>
                <span class="text-indigo-600">cliquez pour parcourir</span>
            </span>
        </p>
        <p class="text-xs text-slate-500">
            {{ acceptLabel }} · {{ maxSizeLabel }} max par fichier
        </p>

        <input
            ref="inputRef"
            type="file"
            :accept="accept"
            :multiple="multiple"
            :disabled="disabled"
            class="hidden"
            @change="onFileInputChange"
        />
    </div>
</template>

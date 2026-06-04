import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { destroy as destroyRoute } from '@/routes/user/controls';

type ControlDefinition = App.Data.User.Control.ControlDefinitionData;

/**
 * Catalog interaction state for the regulatory controls index (Chantier B / B1,
 * domaine B): which control the inline editor edits (null = create), and the
 * soft-delete confirmation flow. The editor form itself lives in
 * {@link useControlDefinitionForm}.
 */
export function useControlsCatalog(): {
    editorOpen: Ref<boolean>;
    editingControl: Ref<ControlDefinition | null>;
    openCreate: () => void;
    openEdit: (control: ControlDefinition) => void;
    confirmOpen: Ref<boolean>;
    deletingControl: Ref<ControlDefinition | null>;
    deleteProcessing: Ref<boolean>;
    askDelete: (control: ControlDefinition) => void;
    performDelete: () => void;
} {
    const editorOpen = ref<boolean>(false);
    const editingControl = ref<ControlDefinition | null>(null);
    const confirmOpen = ref<boolean>(false);
    const deletingControl = ref<ControlDefinition | null>(null);
    const deleteProcessing = ref<boolean>(false);

    function openCreate(): void {
        editingControl.value = null;
        editorOpen.value = true;
    }

    function openEdit(control: ControlDefinition): void {
        editingControl.value = control;
        editorOpen.value = true;
    }

    function askDelete(control: ControlDefinition): void {
        deletingControl.value = control;
        confirmOpen.value = true;
    }

    function performDelete(): void {
        const control = deletingControl.value;

        if (control === null) {
            return;
        }

        router.delete(destroyRoute.url(control.id), {
            preserveScroll: true,
            onStart: () => {
                deleteProcessing.value = true;
            },
            onFinish: () => {
                deleteProcessing.value = false;
            },
            onSuccess: () => {
                confirmOpen.value = false;
                deletingControl.value = null;
            },
        });
    }

    return {
        editorOpen,
        editingControl,
        openCreate,
        openEdit,
        confirmOpen,
        deletingControl,
        deleteProcessing,
        askDelete,
        performDelete,
    };
}

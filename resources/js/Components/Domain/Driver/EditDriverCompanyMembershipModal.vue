<script setup lang="ts">
/**
 * Modal d'édition d'une membership Driver↔Company existante (chantier B).
 *
 * Scope V1 : `joined_at` uniquement. La gestion de `left_at` reste pilotée
 * par le workflow Sortir dédié (qui orchestre la résolution des contrats
 * à venir) — voir `LeaveDriverCompanyModal`.
 *
 * Pattern symétrique aux autres modaux memberships :
 * - props neutres (`driverId, pivotId, companyShortCode, currentJoinedAt,
 *   currentLeftAt`) → réutilisable depuis Driver Show ET Company Show
 * - submit `useForm.patch(updateRoute(...).url)`, `onSuccess: close()` —
 *   Inertia récupère les props fraîches via le redirect back() du
 *   controller, le tableau parent affiche la nouvelle date d'entrée.
 *
 * Validation chronologique côté serveur : si `joined_at > left_at`, le
 * controller throw `ValidationException` avec un message FR explicite
 * sur le champ `joined_at`.
 */
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { update as updateRoute } from '@/routes/user/drivers/memberships';

const props = defineProps<{
    driverId: number;
    pivotId: number;
    companyShortCode: string;
    currentJoinedAt: string;
    currentLeftAt: string | null;
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);

const form = useForm({
    joined_at: props.currentJoinedAt,
});

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    form.patch(updateRoute([props.driverId, props.pivotId]).url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Modifier le rattachement"
        @close="emit('close')"
    >
        <p class="text-sm text-slate-700">
            Modifier la date d'entrée de
            <strong>{{ companyShortCode }}</strong>.
        </p>
        <p
            v-if="currentLeftAt !== null"
            class="mt-1 text-xs text-slate-500"
        >
            Sortie posée le <strong>{{ currentLeftAt }}</strong> — la nouvelle
            date d'entrée doit lui être antérieure ou égale.
        </p>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="edit-membership-joined-at">
                    Date d'entrée
                </FieldLabel>
                <DateInput
                    id="edit-membership-joined-at"
                    v-model="form.joined_at"
                />
                <InputError :message="form.errors.joined_at" />
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close">
                    Annuler
                </Button>
                <Button
                    type="submit"
                    :loading="form.processing"
                    :disabled="form.joined_at === ''"
                >
                    Mettre à jour
                </Button>
            </div>
        </form>
    </Modal>
</template>

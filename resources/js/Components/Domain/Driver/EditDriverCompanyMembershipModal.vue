<script setup lang="ts">
/**
 * Modal d'édition d'une membership Driver↔Company existante (chantiers
 * B + B-bis).
 *
 * Champs : `joined_at` (toujours requis) + `left_at` (optionnel).
 *  - Membership active : `currentLeftAt` est `null`, le champ part vide,
 *    l'utilisateur peut laisser vide ou poser une date de sortie (mais
 *    pour la première sortie d'une membership active, le workflow Sortir
 *    dédié · `LeaveDriverCompanyModal` · est préférable car il gère les
 *    contrats à venir).
 *  - Membership sortie : `currentLeftAt` est la date posée. L'utilisateur
 *    peut la corriger ou l'effacer (réactivation).
 *
 * Validation chronologique côté serveur (`UpdateDriverCompanyMembershipAction`) :
 * `joined_at <= left_at` si `left_at` est posé. Une violation est
 * surfacée comme `ValidationException` sur le champ `joined_at`.
 *
 * Le composant est neutre quant au contexte · réutilisé depuis
 * Driver Show ET Company Show.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    left_at: props.currentLeftAt as string | null,
});

const canSubmit = computed<boolean>(
    () => form.joined_at !== '' && !form.processing,
);

function close(): void {
    open.value = false;
    emit('close');
}

function clearLeftAt(): void {
    form.left_at = null;
}

function submit(): void {
    form.transform((data) => ({
        joined_at: data.joined_at,
        // Normalise les chaînes vides en `null` pour être interprété
        // comme « pas de date de sortie » côté serveur (réactivation).
        left_at: data.left_at === null || data.left_at === '' ? null : data.left_at,
    })).patch(updateRoute([props.driverId, props.pivotId]).url, {
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
            Modifier les dates de rattachement avec
            <strong>{{ companyShortCode }}</strong>.
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

            <div>
                <div class="flex items-center justify-between gap-2">
                    <FieldLabel for="edit-membership-left-at">
                        Date de sortie
                    </FieldLabel>
                    <button
                        v-if="form.left_at !== null && form.left_at !== ''"
                        type="button"
                        class="text-xs text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
                        @click="clearLeftAt"
                    >
                        Effacer
                    </button>
                </div>
                <DateInput
                    id="edit-membership-left-at"
                    v-model="form.left_at"
                />
                <InputError :message="form.errors.left_at" />
                <p
                    v-if="form.left_at === null || form.left_at === ''"
                    class="mt-1 text-xs text-slate-500"
                >
                    Vide = rattachement actif. Effacer la date de sortie
                    posée réactive le rattachement.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close">
                    Annuler
                </Button>
                <Button
                    type="submit"
                    :loading="form.processing"
                    :disabled="!canSubmit"
                >
                    Mettre à jour
                </Button>
            </div>
        </form>
    </Modal>
</template>

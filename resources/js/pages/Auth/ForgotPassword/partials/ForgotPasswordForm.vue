<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { email as passwordEmailRoute } from '@/routes/password';

const form = useForm({
    email: '',
});

// Inline persistent banner: guest layout has no ToastContainer, and the
// user must keep the message visible while checking their mailbox.
// Wording stays neutral to prevent email enumeration (cf. ADR-0011).
const submitted = ref<boolean>(false);

const submit = (): void => {
    form.post(passwordEmailRoute.url(), {
        onSuccess: () => {
            submitted.value = true;
            form.reset('email');
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="submitted"
            role="status"
            aria-live="polite"
            class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
        >
            <CheckCircle2
                :size="20"
                :stroke-width="1.75"
                class="mt-0.5 shrink-0 text-emerald-600"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-1">
                <p class="font-medium">Demande envoyée</p>
                <p>
                    Si l'adresse saisie correspond à un compte, un e-mail
                    contenant un lien de réinitialisation vient d'être
                    envoyé. Vérifiez votre boîte de réception (et vos
                    indésirables). Le lien est valide pendant 60 minutes.
                </p>
            </div>
        </div>

        <form
            class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <p class="text-sm text-slate-600">
                Saisissez votre adresse e-mail. Si elle correspond à un
                compte, vous recevrez un lien de réinitialisation valide
                pendant 60 minutes.
            </p>

            <TextInput
                v-model="form.email"
                type="email"
                label="Adresse e-mail"
                autocomplete="email"
                :error="form.errors.email"
                required
            />

            <Button type="submit" block :loading="form.processing">
                Envoyer le lien de récupération
            </Button>
        </form>
    </div>
</template>

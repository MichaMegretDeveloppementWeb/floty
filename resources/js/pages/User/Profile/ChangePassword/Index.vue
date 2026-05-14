<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ChangePasswordForm from './Partials/ChangePasswordForm.vue';
</script>

<template>
    <Head title="Changer mon mot de passe" />

    <UserLayout>
        <div class="mx-auto w-full max-w-xl py-8">
            <header class="mb-6">
                <p class="eyebrow text-slate-500">Profil</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    Changer mon mot de passe
                </h1>
                <p class="mt-2 text-sm text-slate-600">
                    Pour votre sécurité, le changement de mot de passe
                    déconnectera automatiquement vos sessions actives sur
                    vos autres appareils.
                </p>
            </header>

            <ChangePasswordForm />
        </div>
    </UserLayout>
</template>

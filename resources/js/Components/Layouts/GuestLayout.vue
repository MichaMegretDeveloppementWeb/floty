<script setup lang="ts">
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useFlashToasts } from '@/Composables/Shared/useFlashToasts';

// Branche le pont flash session → toasts UI sur les pages guest
// (Login, ForgotPassword, ResetPassword). Sans ce layout, les toasts
// posés en session par le backend (`back()->with('toast-warning', ...)`
// par exemple sur 429 intercepté dans bootstrap/app.php) tombent
// silencieusement faute de consommateur côté front. Cf. UserLayout.vue
// qui fait la même chose pour les pages authentifiées.
//
// Pas de wrapper visuel · le layout délègue tout au slot pour ne pas
// imposer de structure aux pages (chacune a déjà son design centré).
useFlashToasts();
</script>

<template>
    <slot />
    <ToastContainer />
</template>

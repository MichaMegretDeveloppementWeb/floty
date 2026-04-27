import { createInertiaApp, router } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Floty';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#0f172a',
    },
});

// Erreurs HTTP non interceptées par le handler Laravel — rare avec
// `bootstrap/app.php` configuré (BaseAppException → flash, 419/403
// → toast, 404/500/503 → page Errors/Index). Si on tombe ici, c'est
// un vrai bug : on log pour qu'il soit visible côté navigateur.
router.on('httpException', (event) => {
    console.error('Inertia HTTP exception', event.detail.response);
});

// Erreurs réseau (pas de connexion, timeout, CORS) côté Inertia — pas
// de visibilité utilisateur native, donc on log explicitement.
router.on('networkError', (event) => {
    console.error('Inertia network error', event.detail.error);
});

// Erreurs validation (réponse 422 d'un FormRequest Laravel) — déjà
// poussées dans `useForm.errors` côté Inertia. Pas de toast à
// déclencher ici, les composants affichent les erreurs via leur slot
// `InputError`.
router.on('error', () => {
    // no-op intentionnel
});

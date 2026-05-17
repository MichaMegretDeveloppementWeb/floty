import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Floty';

const pageImports = import.meta.glob('./pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#0f172a',
    },
    resolve: async (name) => {
        const importer = pageImports[`./pages/${name}.vue`];

        if (!importer) {
            throw new Error(`Page Inertia introuvable · "${name}".`);
        }

        const module = (await importer()) as { default: unknown };

        return (module.default ?? module) as never;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});

// Erreurs HTTP non interceptées par le handler Laravel - rare avec
// `bootstrap/app.php` configuré (BaseAppException → flash, 419/403
// → toast, 404/500/503 → page Errors/Index). Si on tombe ici, c'est
// un vrai bug · on log pour qu'il soit visible côté navigateur.
router.on('httpException', (event) => {
    console.error('Inertia HTTP exception', event.detail.response);
});

// Erreurs réseau (pas de connexion, timeout, CORS) côté Inertia - pas
// de visibilité utilisateur native, donc on log explicitement.
router.on('networkError', (event) => {
    console.error('Inertia network error', event.detail.error);
});

// Erreurs validation (réponse 422 d'un FormRequest Laravel) - déjà
// poussées dans `useForm.errors` côté Inertia. Pas de toast à
// déclencher ici, les composants affichent les erreurs via leur slot
// `InputError`.
router.on('error', () => {
    // no-op intentionnel
});

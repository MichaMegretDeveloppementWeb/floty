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

// Catch HTTP exceptions the Laravel handler does not redirect/flash.
router.on('httpException', (event) => {
    console.error('Inertia HTTP exception', event.detail.response);
});

// Network errors (no connection, timeout, CORS) have no native UI; log them.
router.on('networkError', (event) => {
    console.error('Inertia network error', event.detail.error);
});

// 422 validation errors are surfaced through useForm.errors/InputError.
router.on('error', () => {
    // intentional no-op
});

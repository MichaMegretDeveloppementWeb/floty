import { createInertiaApp } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Floty';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#0f172a',
    },
});

import { vi } from 'vitest';

/**
 * Global mock for `@inertiajs/vue3` so tests that consume `router`
 * (server-side Index composables, useServerTableState, etc.) can spy on
 * navigation calls without booting a real Inertia app.
 */
vi.mock('@inertiajs/vue3', () => {
    const router = {
        reload: vi.fn(),
        visit: vi.fn(),
        replace: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        on: vi.fn(() => () => {}),
        cancelAll: vi.fn(),
    };

    return {
        router,
        Link: { template: '<a><slot/></a>' },
        Head: { template: '<div><slot/></div>' },
        useForm: vi.fn(),
    };
});

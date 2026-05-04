import { vi } from 'vitest';

/**
 * Mock global de `@inertiajs/vue3` pour les tests Vitest qui consomment
 * `router` (notamment `useServerTableState` et les composables Index
 * server-side, cf. ADR-0020).
 *
 * Les tests qui n'importent pas `@inertiajs/vue3` ne sont pas affectés.
 *
 * Utilisation dans un test :
 *   import { router } from '@inertiajs/vue3';
 *   import { vi } from 'vitest';
 *   beforeEach(() => { vi.mocked(router.reload).mockClear(); });
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

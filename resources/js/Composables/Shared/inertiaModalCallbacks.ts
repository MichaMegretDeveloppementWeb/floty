import type { Ref } from 'vue';

/**
 * Inertia modal callback helper: closes one or more `Ref<boolean>` on visit `onSuccess`.
 *
 * Used by Vehicle/Show forms (and beyond) to avoid repeating the `preserveScroll + onSuccess close` pattern.
 *
 * Simple usage: `form.post(url, closeOnSuccess(open));`
 * Multiple refs (main modal + confirmation): `form.post(url, closeOnSuccess(open, confirmationOpen));`
 *
 * Returns the exact Inertia options shape so callers can spread for local extension:
 * `form.post(url, { ...closeOnSuccess(open), onError: () => ... });`
 */
export function closeOnSuccess(...refs: Ref<boolean>[]): {
    preserveScroll: true;
    onSuccess: () => void;
} {
    return {
        preserveScroll: true,
        onSuccess: () => {
            for (const r of refs) {
                r.value = false;
            }
        },
    };
}

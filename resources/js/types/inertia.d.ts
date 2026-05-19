import '@/types/generated/generated';

/**
 * Floty PageProps: shared props exposed by `HandleInertiaRequests` on
 * every Inertia page, strictly typed against the auto-generated Spatie
 * Data DTOs (cf. `@/types/generated/generated.d.ts`).
 *
 * Consumed from Vue via `usePage().props.xxx` with autocompletion.
 */
declare module '@inertiajs/core' {
    interface PageProps {
        appName: string;
        auth: {
            user: App.Data.Auth.CurrentUserData | null;
        };
        flash: App.Data.Shared.FlashData;
    }
}

export {};

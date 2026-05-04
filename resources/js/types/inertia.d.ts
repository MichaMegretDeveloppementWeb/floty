import '@/types/generated/generated';

/**
 * PageProps Floty - shared props exposées par `HandleInertiaRequests`
 * à chaque page Inertia, typées strictement via les DTOs Spatie Data
 * générés automatiquement (cf. `@/types/generated/generated.d.ts`).
 *
 * Côté Vue on consomme via `usePage().props.xxx` avec autocomplétion.
 */
declare module '@inertiajs/core' {
    interface PageProps {
        appName: string;
        auth: {
            user: App.Data.Auth.CurrentUserData | null;
        };
        flash: App.Data.Shared.FlashData;
        fiscal: App.Data.Shared.FiscalSharedData;
    }
}

export {};

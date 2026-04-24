import type { Auth, Flash } from '@/types/auth';

/**
 * PageProps Floty — shared props exposées par `HandleInertiaRequests`
 * à chaque page Inertia, typées strictement.
 *
 * Sont ajoutés ici :
 *   - au fur et à mesure des phases (ex. `fiscalYear` en phase 02.18) ;
 *   - les props spécifiques qui deviennent communes à plusieurs pages.
 *
 * Côté Vue on consomme via `usePage().props.xxx` avec autocomplétion.
 */
declare module '@inertiajs/core' {
    interface PageProps {
        appName: string;
        auth: Auth;
        flash: Flash;
        fiscal: {
            currentYear: number;
            availableYears: number[];
        };
    }
}

export {};

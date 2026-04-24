/**
 * Utilisateur connecté — shape minimal exposé par le middleware
 * `HandleInertiaRequests` en phase 01. Sera remplacé en phase 03 par
 * un DTO généré `CurrentUserData` (Spatie Laravel Data → TS Transformer).
 */
export type CurrentUser = {
    id: number;
    name: string;
    email: string;
};

/**
 * Bloc `auth` des shared props Inertia.
 * `user === null` quand aucun utilisateur n'est authentifié.
 */
export type Auth = {
    user: CurrentUser | null;
};

/**
 * Quatre canaux de flash message — un par ton de Toast du design system.
 * Un canal vaut `null` quand aucun message n'est en attente.
 */
export type Flash = {
    success: string | null;
    error: string | null;
    warning: string | null;
    info: string | null;
};

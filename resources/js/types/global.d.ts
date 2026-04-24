// Extension Vite — typage strict des variables `import.meta.env.*`
// utilisées côté client. On ajoute ici chaque variable `VITE_*` exposée
// par `.env` au fur et à mesure qu'elle est consommée.

declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

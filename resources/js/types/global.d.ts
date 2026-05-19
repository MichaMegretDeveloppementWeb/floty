// Vite extension: strict typing of `import.meta.env.*` variables used
// on the client. Add each `VITE_*` variable exposed via `.env` as it
// becomes consumed.

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

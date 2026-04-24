# Task 00.09 — Configurer TypeScript en strict mode

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04
> **Estimation** : 30 min
> **Références règles** : `typescript-dto.md` § « Configuration TypeScript stricte »

---

## Objectif

Durcir la config TypeScript Floty au-delà du starter : strict mode + options avancées qui éliminent les classes de bugs (accès aveugles au tableau, nullabilité implicite, etc.).

## Méthode

1. Éditer `tsconfig.json` à la racine. Partir de la config du starter et **ajouter/vérifier** les options suivantes (cf. `typescript-dto.md`) :
   ```jsonc
   {
     "compilerOptions": {
       // Bases déjà présentes
       "target": "ES2022",
       "module": "ESNext",
       "moduleResolution": "bundler",

       // Strict (obligatoire)
       "strict": true,
       "noImplicitAny": true,
       "strictNullChecks": true,
       "strictFunctionTypes": true,
       "strictBindCallApply": true,
       "strictPropertyInitialization": true,
       "noImplicitThis": true,
       "alwaysStrict": true,

       // Avancé (obligatoire Floty)
       "noUnusedLocals": true,
       "noUnusedParameters": true,
       "noImplicitReturns": true,
       "noFallthroughCasesInSwitch": true,
       "noUncheckedIndexedAccess": true,
       "exactOptionalPropertyTypes": true,
       "noImplicitOverride": true,

       // Imports et résolution
       "esModuleInterop": true,
       "forceConsistentCasingInFileNames": true,
       "skipLibCheck": true,
       "isolatedModules": true,
       "resolveJsonModule": true,
       "allowSyntheticDefaultImports": true,

       // Paths
       "baseUrl": ".",
       "paths": {
         "@/*": ["resources/js/*"],
         "@css/*": ["resources/css/*"]
       },

       "types": ["vite/client", "node"]
     },
     "include": [
       "resources/js/**/*.ts",
       "resources/js/**/*.vue",
       "resources/js/**/*.d.ts"
     ],
     "exclude": ["node_modules", "public", "vendor"]
   }
   ```
2. Lancer `npm run build` → doit passer (le starter cleanup a éliminé le code problématique).
3. Lancer `npx vue-tsc --noEmit` → pas d'erreur.
4. Ajouter dans `package.json` si absent :
   ```json
   "scripts": { "types:check": "vue-tsc --noEmit" }
   ```
5. Commit.

## Critères de validation

- [ ] `tsconfig.json` contient les 7 options strictes obligatoires.
- [ ] `npm run build` passe.
- [ ] `npm run types:check` passe.
- [ ] Pas d'`any` implicite dans le peu de code TS restant du starter post-cleanup.

## Pièges identifiés

- **`exactOptionalPropertyTypes`** : peut remonter des erreurs sur les DTO générés par Spatie si la génération produit `foo?: string | undefined` au lieu de `foo?: string`. À vérifier, sinon config de génération Spatie à ajuster.
- **`noUncheckedIndexedAccess`** : change le type `T[n]` en `T | undefined`. Peut forcer des `!` ou des `if (x)` un peu partout. **C'est le but** — c'est ce qu'on veut pour éviter les bugs d'accès aveugles.
- **Erreurs sur `@inertiajs/vue3`** : si la lib a des types imparfaits, on `skipLibCheck` s'occupe.

## Références

- `implementation-rules/typescript-dto.md` § « Configuration TypeScript stricte »
- ADR-0008 § « TypeScript 6 strict »

# Task 00.10 — Configurer ESLint + Prettier

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04, 00.09
> **Estimation** : 30 min

---

## Objectif

ESLint + Prettier sont déjà installés par le starter (cf. `package.json` : `eslint@^9`, `prettier@^3`, `@vue/eslint-config-typescript`). Vérifier et **ajuster la config** pour matcher nos règles Floty.

## Méthode

1. Vérifier `eslint.config.js` à la racine. Il doit couvrir :
   - `.ts`, `.vue`, `.d.ts` dans `resources/js/**`.
   - Préréglages Vue 3 + TypeScript.
   - Règles Floty :
     - `@typescript-eslint/no-unused-vars`: `error`
     - `@typescript-eslint/no-explicit-any`: `error`
     - `vue/multi-word-component-names`: `error` (cohérent avec `conventions-nommage.md` § Composants Vue)
     - `vue/component-api-style`: `['error', ['script-setup']]` (Composition API stricte)
     - `@typescript-eslint/consistent-type-imports`: `['error', { prefer: 'type-imports' }]`
2. Vérifier `.prettierrc` :
   ```json
   {
     "semi": false,
     "singleQuote": true,
     "trailingComma": "all",
     "printWidth": 100,
     "tabWidth": 2,
     "plugins": ["prettier-plugin-tailwindcss"]
   }
   ```
   *prettier-plugin-tailwindcss déjà dans le starter → trie les classes Tailwind automatiquement.*
3. Lancer `npm run lint` → auto-fix.
4. Lancer `npm run format` → formate.
5. Lancer `npm run lint:check` et `npm run format:check` → tous passent.
6. Commit.

## Critères de validation

- [ ] `eslint.config.js` inclut les règles Floty.
- [ ] `.prettierrc` présent et cohérent.
- [ ] `npm run lint:check` + `npm run format:check` passent.

## Pièges identifiés

- **Conflit ESLint/Prettier** : s'assurer que `eslint-config-prettier` est bien inclus (désactive les règles ESLint qui entrent en conflit avec Prettier). Le starter l'inclut déjà.
- **`multi-word-component-names`** : notre convention n'autorise pas `<Button />` direct comme nom de fichier — on dit `Button.vue` mais dans `Components/Ui/Button/` donc pas de clash. Si ESLint râle, c'est que le composant n'est pas dans un sous-dossier correctement nommé.

## Références

- `implementation-rules/conventions-nommage.md`
- `implementation-rules/vue-composants.md`

# Task 00.06 — Configurer Vitest + Vue Test Utils + Testing Library

> **Phase** : 00 — Init
> **Statut** : à faire
> **Dépendances** : 00.04
> **Estimation** : 45 min
> **Fiche projet** : [`docs/vitest-configuration.md`](../../docs/vitest-configuration.md)
> **Références règles** : `tests-frontend.md`

---

## Objectif

Mettre en place la stack de tests frontend Floty : **Vitest + Vue Test Utils + @pinia/testing + happy-dom + @testing-library/vue** (ce dernier en complément pour les tests comportementaux).

## Méthode

1. Installer (en dev) :
   ```bash
   npm install -D vitest @vue/test-utils @pinia/testing happy-dom @testing-library/vue @testing-library/dom
   npm install -D @vitest/coverage-v8 @vitest/ui
   ```
2. Créer `vitest.config.ts` à la racine — configuration cf. `tests-frontend.md` § « Configuration Vitest » :
   - `environment: 'happy-dom'`
   - `setupFiles: ['./resources/js/test-setup.ts']`
   - `include: ['resources/js/**/*.spec.ts']`
   - Coverage v8 avec exclusions (generated.d.ts, configs, test-setup).
3. Créer `resources/js/test-setup.ts` — contenu cf. `tests-frontend.md` § « Setup global » :
   - Mock global `route()` (sera moins utile avec Wayfinder mais reste utile pour quelques cas).
   - Plugin `createTestingPinia` global.
   - Stub `<Link>` Inertia global.
4. Créer un test smoke `resources/js/test-setup.spec.ts` qui vérifie que Vitest + happy-dom fonctionnent :
   ```ts
   import { describe, it, expect } from 'vitest'
   describe('Vitest setup', () => {
     it('runs', () => {
       expect(true).toBe(true)
     })
     it('has DOM', () => {
       const div = document.createElement('div')
       expect(div).toBeInstanceOf(HTMLElement)
     })
   })
   ```
5. Ajouter dans `package.json` :
   ```json
   "scripts": {
     "test": "vitest",
     "test:ci": "vitest run",
     "test:coverage": "vitest run --coverage",
     "test:ui": "vitest --ui"
   }
   ```
6. Lancer `npm run test:ci` → doit passer le smoke test.
7. Commit.

## Critères de validation

- [ ] `npm run test:ci` passe.
- [ ] `npm run test:coverage` génère un rapport HTML dans `coverage/`.
- [ ] `vitest.config.ts` cohérent avec `tests-frontend.md`.
- [ ] `resources/js/test-setup.ts` cohérent avec `tests-frontend.md`.
- [ ] Le test smoke est dans `resources/js/test-setup.spec.ts` (sera retiré ou enrichi plus tard).

## Pièges identifiés

- **`happy-dom` vs `jsdom`** : on choisit happy-dom pour la rapidité (cf. `tests-frontend.md`).
- **`@testing-library/vue`** : optionnel mais utile pour les tests comportementaux. À utiliser au cas par cas, pas systématiquement.
- **Coverage v8** : provider natif Node.js, pas besoin d'instrumenter.

## Références

- `implementation-rules/tests-frontend.md` — règles complètes
- `docs/vitest-configuration.md` — config exacte Floty
- ADR-0008 (Vitest dans la stack)

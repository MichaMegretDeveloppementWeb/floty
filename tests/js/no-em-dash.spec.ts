import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { glob } from 'glob';
import { describe, expect, it } from 'vitest';

/**
 * Garde-fou anti-régression em-dash U+2014 (Lot 7 D10 · F-41-001/002 +
 * F-42-005). Complète la règle ESLint `no-restricted-syntax` du
 * `eslint.config.js` (qui ne couvre que Literal/TemplateElement, pas
 * les commentaires ni les attributs Vue).
 *
 * Cf. mémoire `feedback_no_em_dash` · le caractère `—` (U+2014) est
 * interdit partout dans le code Floty. Le `·` U+00B7 (point central)
 * reste autorisé.
 *
 * Si ce test fail, exécuter ·
 *   `grep -rn $'\xe2\x80\x94' resources/js/`
 * puis remplacer chaque occurrence par `·` / `:` / refonte syntaxique
 * selon contexte.
 */
const FRONTEND_ROOT = resolve(process.cwd(), 'resources/js');
const EM_DASH = '—';

describe('No em-dash U+2014 in frontend source files', () => {
    it('does not contain em-dash in any .ts/.tsx/.vue file', async () => {
        const files = await glob('**/*.{ts,tsx,vue}', { cwd: FRONTEND_ROOT, absolute: true });

        const offenders = files.filter((f) => readFileSync(f, 'utf8').includes(EM_DASH));

        expect(offenders, `Em-dash U+2014 trouvé dans ces fichiers · ${offenders.join(', ')}`).toEqual([]);
    });
});

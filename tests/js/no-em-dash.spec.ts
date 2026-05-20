import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { glob } from 'glob';
import { describe, expect, it } from 'vitest';

/**
 * Anti-regression guard for em-dash U+2014. Complements the ESLint
 * `no-restricted-syntax` rule which only covers Literal/TemplateElement,
 * not comments or Vue attributes. The `·` middle dot (U+00B7) is allowed.
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

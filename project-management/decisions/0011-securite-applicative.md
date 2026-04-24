# ADR-0011 — Sécurité applicative minimum

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Floty est un produit B2B SaaS fiscal. Les données traitées (fiscalité, PII, SIREN, planning flottes) justifient un niveau d'exigence de sécurité supérieur à un outil interne. L'ADR-0008 a tranché la stack (Laravel 13, Inertia v3, Hostinger mutualisé) sans expliciter les garde-fous sécurité applicative. Le rapport-001 a identifié ce silence comme P1.

Cet ADR fixe le **socle minimum V1**. V2+ peut étendre (2FA, SSO, audit log avancé, rate limiting plus strict).

---

## Décision

### Périmètre V1

Seul est couvert le périmètre **applicatif** (niveau Laravel + front). Les couches réseau, OS, physique sont à la charge de Hostinger (sous-traitant — cf. ADR-0010) et ne sont pas dans le scope de cet ADR.

### 1. HTTPS obligatoire

- Certificat Let's Encrypt configuré via Hostinger (inclus dans le plan Business).
- Middleware `\Illuminate\Http\Middleware\TrustProxies` + forçage HTTPS en prod via `URL::forceScheme('https')` dans `AppServiceProvider::boot` (conditionné par `app()->environment('production')`).
- Header HSTS : `Strict-Transport-Security: max-age=31536000; includeSubDomains` (1 an).

### 2. Authentification et sessions

- Mots de passe hashés `bcrypt` (défaut Laravel 13), coût par défaut.
- Politique de mot de passe (au formulaire de reset) : **min 12 caractères, pas de règle de complexité** (recommandation NIST 2024 : priorité à la longueur sur la complexité).
- Session driver `database` (table `sessions`) — cohérent avec ADR-0008.
- Cookie session : `secure = true` en prod, `http_only = true`, `same_site = 'lax'`.
- Durée session : 120 minutes d'inactivité avant expiration (`config/session.php`).
- Regeneration de session ID au login (défaut Laravel).
- **Pas de « remember me »** en V1 (simplification conservatoire ; rouvrable V2 si besoin UX).

### 3. Protection contre le brute force login

- Middleware `throttle` Laravel sur la route login : **5 tentatives / 15 min** par couple (IP, email).
- Blocage silencieux (message générique « identifiants invalides ») — ne jamais indiquer si c'est l'email ou le mot de passe qui est faux.
- Journalisation des tentatives échouées dans le canal `auth` (10 lignes par tentative max, agrégé).

### 4. CSRF

- Protection CSRF native Laravel via le middleware `VerifyCsrfToken`.
- Inertia v3 gère le token automatiquement dans les submit via `useForm`.
- Erreur 419 (session/CSRF expiré) capturée par un handler global (cf. `gestion-erreurs.md` § 419) qui redirige vers le login avec un flash `warning`.

### 5. Injection

- **SQL** : Eloquent ou Query Builder paramétrés uniquement. Aucun `DB::raw` avec interpolation de variable utilisateur.
- **XSS** : Vue 3 échappe par défaut via `{{ }}`. Interdiction d'utiliser `v-html` avec du contenu utilisateur non-trusted. CI grep sur `v-html` + revue systématique.
- **Path traversal** (upload PDF) : chemins sous `storage/app/private/declarations/` jamais composés à partir de saisie utilisateur — toujours via `DeclarationPdf::getFilePath()` qui utilise l'id en base + UUID.

### 6. Headers de sécurité HTTP

Middleware `SecureHeaders` (à créer en phase 01 ou 13) applique en prod :

| Header | Valeur |
|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Content-Security-Policy` | voir ci-dessous |

**CSP V1** — politique modérée pour ne pas bloquer le dev :
```
default-src 'self';
script-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data:;
font-src 'self' data:;
connect-src 'self';
frame-ancestors 'none';
base-uri 'self';
form-action 'self';
```

`'unsafe-inline'` sur `style-src` est conservé pour Tailwind inline runtime (sera tightened V2 avec nonces si besoin).

### 7. Autorisation

- **Rôle unique V1** : tout utilisateur authentifié peut tout faire (gestionnaire flotte). Policies Laravel par entité (VehiclePolicy, CompanyPolicy, etc.) qui retournent toujours `true` en V1 mais existent pour faciliter V2 (rôles).
- Aucune donnée exposée au non-authentifié sauf les pages publiques (landing, mentions légales — hors UI Kit).

### 8. Audit des dépendances

- `composer audit` lancé en CI GitHub Actions sur chaque push.
- `npm audit --omit=dev --audit-level=high` idem.
- Dépendances obsolètes review trimestrielle.

### 9. Upload de fichiers

Le seul upload V1 prévu est éventuellement la photo véhicule (optionnelle). Règles :
- Whitelist MIME : `image/jpeg`, `image/png`, `image/webp` uniquement.
- Max 2 Mo par fichier.
- Réécriture via Intervention Image (si package installé) ou stockage tel quel après validation MIME stricte.
- Chemin `storage/app/public/vehicles/photos/{uuid}.{ext}` — jamais de nom de fichier utilisateur.

### 10. Secrets et configuration

- `.env` jamais committé (cf. `.gitignore`).
- Secrets de production stockés dans le panneau Hostinger (variables d'environnement) ou `.env` local à l'hébergement, jamais dans un repo.
- Pas de hardcode de clés API dans le code ou les seeders.
- Rotation `APP_KEY` : à ne pas faire en cours de vie d'un déploiement (invalide les sessions + chiffrement des données chiffrées — mais V1 n'en chiffre aucune).

---

## Alternatives écartées

1. **2FA V1** — surcoût UX et support, population restreinte (quelques gestionnaires). À rouvrir V2.
2. **SSO / OAuth** — idem, population trop petite V1.
3. **WAF Hostinger** — non disponible sur Business mutualisé. À rouvrir si migration VPS.
4. **CSP stricte sans `'unsafe-inline'`** — impliquerait des nonces, complexifie le bundling V1. Tightened V2.

---

## Conséquences

- Une tâche phase 13 (ex. 13.14) ajoute le middleware `SecureHeaders` et configure les headers HTTP.
- Une tâche phase 03 (auth) intègre le throttling login via le middleware Laravel standard.
- Le CI GitHub Actions (phase 00.09) inclut `composer audit` et `npm audit`.
- La politique de mot de passe min 12 caractères est appliquée dans `StoreUserRequest` et `ResetPasswordRequest` (phase 03).
- Un test Feature smoke vérifie la présence des headers de sécurité sur la réponse HTTP (phase 13).

---

## Références

- ADR-0008 (stack technique V1)
- ADR-0010 (RGPD et conservation)
- OWASP Top 10 2021 (référentiel de priorisation)
- NIST SP 800-63B (recommandations modernes mots de passe)
- `implementation-rules/gestion-erreurs.md` § 419 (handler CSRF)
- `rapport-001.md` P1.1 (justification de cet ADR)

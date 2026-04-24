# ADR-0012 — Authentification et gestion des comptes utilisateur

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

L'ADR-0007 a fixé le périmètre V1 MVP en mentionnant l'auth de manière éclatée (« reset manuel du mot de passe », pas de starter kit). L'ADR-0011 vient de cadrer la sécurité applicative. Il reste à consolider dans un ADR dédié l'ensemble des décisions **gestion des comptes** : création, login, logout, reset password, 2FA, verification email.

Population V1 : petite équipe de gestionnaires flotte (3-5 personnes max) de la société de location Renaud. Pas de self-registration publique.

---

## Décision

### 1. Création de comptes — manuelle par le prestataire

V1 n'expose **aucun formulaire public d'inscription**. Les comptes sont créés manuellement :

- Commande Artisan `php artisan floty:user:create {email} {name}` qui provisionne un compte avec un mot de passe temporaire généré aléatoirement.
- Le mot de passe temporaire est imprimé dans le terminal une seule fois ; la commande n'envoie **pas** d'email V1 (simplification ; ré-ouverture V2 si volume > 10 comptes).
- Au premier login, l'utilisateur est **forcé à changer son mot de passe** via une page dédiée (flag `must_change_password` sur le modèle `User`).

### 2. Login

- Route `/login` publique, méthode POST.
- Champs : email, password.
- Message d'erreur générique (ne divulgue pas si l'email existe).
- Throttle : 5 tentatives / 15 min (cf. ADR-0011 § 3).
- Journalisation canal `auth` des tentatives réussies et échouées.
- Redirect vers `/app/dashboard` après login (sauf si `must_change_password` → redirect `/profile/change-password`).

### 3. Logout

- Route `/logout` POST (CSRF-protégée).
- Invalide la session courante + régénère le token CSRF.
- Redirect vers `/` (landing publique).

### 4. Reset password — self-service en V1

Contrairement à l'ADR-0007 initial qui évoquait un reset manuel, V1 intègre le **flux self-service standard Laravel** :

- Route `/forgot-password` (email) → envoie un lien signé à durée limitée (60 min).
- Route `/reset-password/{token}` → permet de saisir un nouveau mot de passe.
- Le token est signé Laravel (HMAC) + expiré après 60 min + single-use (invalidé après utilisation).
- Throttle : 3 demandes / 60 min par email.

Motivation vs ADR-0007 : le reset manuel via prestataire est un point de friction pour le client (délai humain, indisponibilité week-end). Le flux self-service Laravel est mature et peu risqué.

### 5. Verification email — non-activée V1

La colonne `email_verified_at` est présente dans la table `users` (scaffold Laravel) mais **le middleware `verified` n'est pas appliqué** aux routes V1. Motivation :

- V1 = population de confiance (quelques comptes créés par le prestataire manuellement).
- Email vérifié au moment de la création du compte (le prestataire confirme avec le client).
- Activable V2 sans migration (colonne déjà là).

### 6. 2FA — non-activée V1

Cf. ADR-0011. Rouvrable V2.

### 7. Changement de mot de passe (connecté)

- Page `/profile/change-password` accessible à tout utilisateur connecté.
- Demande : mot de passe actuel + nouveau mot de passe (2 fois).
- Min 12 caractères (cf. ADR-0011 § 2).
- Invalide toutes les **autres** sessions de cet utilisateur après changement (sécurité).

### 8. Profil utilisateur

V1 expose un profil minimal :
- Nom (editable).
- Email (editable — déclenche re-vérification si `verified` est activé V2+).
- Mot de passe (via flux ci-dessus).
- Bouton « Déconnexion ».

Pas de préférences UI, pas de langue (app FR), pas de timezone (Europe/Paris fixe).

### 9. Modèle `User`

Schéma minimal (scaffold Laravel 13 + ajouts Floty) :

```php
users (
  id bigint PK,
  email varchar UNIQUE,
  email_verified_at datetime NULL,  // présente mais non utilisée V1
  name varchar,
  password varchar,  // bcrypt
  must_change_password boolean DEFAULT false,
  last_login_at datetime NULL,
  remember_token varchar NULL,  // non utilisé V1
  created_at, updated_at
)
```

Pas de soft-delete V1 (cohérent ADR-0010 pour la conservation 10 ans : un utilisateur désactivé reste en base avec un flag `is_active = false` à ajouter si V2 introduit la désactivation).

### 10. Policy et rôles

V1 = rôle unique « gestionnaire flotte ». Tout utilisateur authentifié peut tout faire. Les `Policy` Laravel par entité existent mais retournent `true` partout (cf. ADR-0011 § 7). V2 introduit éventuellement `admin` / `gestionnaire` / `lecture seule`.

---

## Alternatives écartées

1. **Starter Kit Laravel Jetstream/Breeze** — écarté par ADR-0008 (trop d'opinions embarquées, Blade partout, difficile à adapter au design system Floty).
2. **Reset manuel par le prestataire** — écarté (friction client, cf. § 4).
3. **2FA dès V1** — écarté (surcoût UX, population restreinte).
4. **Self-registration publique** — écarté (pas de besoin métier V1, risque spam).
5. **Auth via Google OAuth** — écarté (pas dans le scope V1 ; retour client = pas demandé).

---

## Conséquences

- La phase 03 (auth custom) implémente :
  - Routes `/login`, `/logout`, `/forgot-password`, `/reset-password/{token}`, `/profile/change-password`.
  - Controllers, FormRequests, Policies associées.
  - Commande Artisan `floty:user:create`.
  - Middleware `ForceChangePassword` qui redirige vers `/profile/change-password` si `must_change_password = true`.
  - Throttle rules cf. ADR-0011.
  - Pages Vue minimales (Login, ForgotPassword, ResetPassword, ChangePassword, Profile).
- Les factories tests (phase 03) incluent un `UserFactory` avec état `mustChangePassword()`.
- L'email de reset password utilise le mailer Laravel configuré (transport à confirmer client — probablement SMTP Hostinger).

---

## Références

- ADR-0007 (périmètre V1 MVP — auth éclatée précédemment)
- ADR-0008 (stack technique — pas de starter kit)
- ADR-0010 (RGPD — logs auth 12 mois)
- ADR-0011 (sécurité applicative — throttle, politique mot de passe)
- `implementation-rules/gestion-erreurs.md` (handler 419 CSRF)
- `rapport-001.md` P1.1 (justification de cet ADR)

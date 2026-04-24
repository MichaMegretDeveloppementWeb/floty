# ADR-0010 — RGPD et conservation des données

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Floty manipule des données à caractère personnel :

- **SIREN / SIRET / raison sociale** des entreprises utilisatrices (données B2B mais nominatives par les représentants légaux).
- **Noms et prénoms des conducteurs** (salariés des entreprises utilisatrices).
- **Adresses et e-mails de contact** des entreprises utilisatrices et des gestionnaires Floty.
- **Immatriculations** (considérées comme données personnelles au sens RGPD en France depuis l'avis CNIL 2017).

ADR-0003 impose par ailleurs une conservation **10 ans** des PDF de déclarations et de leurs snapshots JSON (obligation de justifier un contrôle fiscal rétroactif : CGI art. L. 102 B).

Un produit SaaS B2B fiscal ayant cette combinaison (PII + conservation 10 ans + sous-traitant hébergeur externe) doit documenter explicitement sa posture RGPD. Le rapport-001 a identifié l'absence de cet ADR comme P1.

---

## Décision

### Rôles RGPD en V1

| Rôle RGPD | Entité | Justification |
|---|---|---|
| **Responsable de traitement** | Le client Renaud (société de location) | Il collecte et exploite les données pour un intérêt légitime métier (calcul fiscal de sa flotte) |
| **Sous-traitant** (art. 28 RGPD) | Hostinger (hébergement mutualisé) | Contrat DPA disponible sur Hostinger, à archiver par le client |
| **Sous-traitant de niveau 2** | Prestataire Floty (Micha MEGRET) pour la maintenance | DPA à rédiger et annexer au contrat de prestation |

V1 ne prévoit **aucun partage de données avec un tiers externe** (pas d'API publique, pas d'analytics externe, pas de CDN externe avec PII dans l'URL).

### Finalités de traitement (à déclarer au registre du responsable)

1. Gestion opérationnelle de la flotte partagée (affectation véhicule-entreprise-conducteur).
2. Calcul des taxes CO₂ et polluants au prorata réel d'utilisation par entreprise utilisatrice.
3. Génération et conservation des déclarations fiscales justificatives.

### Base légale

- **Exécution contractuelle** (art. 6.1.b RGPD) pour les données strictement nécessaires à la facturation et à la déclaration fiscale.
- **Obligation légale** (art. 6.1.c RGPD) pour la conservation 10 ans des déclarations fiscales (CGI art. L. 102 B).
- **Intérêt légitime** (art. 6.1.f RGPD) pour les données de planning opérationnel (quel véhicule à quelle entreprise, quel conducteur).

### Durées de conservation

| Catégorie | Durée | Justification |
|---|---|---|
| Déclarations fiscales PDF + snapshots JSON | **10 ans** après clôture de l'exercice | CGI art. L. 102 B (obligation comptable) |
| Attributions et indisponibilités (tables opérationnelles) | **10 ans** après clôture de l'exercice concerné | Nécessaire au rejeu d'un calcul fiscal lors d'un contrôle |
| Caractéristiques véhicule (incluant historisation) | Durée de vie du véhicule + 10 ans | Même justification |
| Conducteurs désactivés (soft-delete, `deactivated_at`) | **10 ans** après dernière activité sur une attribution | Nécessaire pour rejouer les calculs passés |
| Companies désactivées (soft-delete) | **10 ans** après dernière déclaration | Idem |
| Logs applicatifs (canal `daily`) | 90 jours | Durée technique standard |
| Logs fiscaux (canal `fiscal`) | 10 ans | Traçabilité du calcul |
| Logs auth (tentatives login, reset password) | 12 mois | Recommandation CNIL |

### Droits des personnes concernées — mise en œuvre V1

| Droit | Mise en œuvre |
|---|---|
| **Accès** | Pas d'interface self-service en V1 ; réponse manuelle par le responsable de traitement via extraction admin (délai légal 30 j) |
| **Rectification** | Via l'interface Floty connectée (gestionnaire flotte édite ses entreprises, conducteurs, véhicules) |
| **Effacement** | **Limité** : tant qu'une personne apparaît dans une attribution des 10 dernières années, l'effacement est refusé au titre de l'obligation légale comptable (art. 17.3.b RGPD). Après 10 ans, purge automatique par tâche Artisan programmée (cf. phase 13) |
| **Portabilité** | Export CSV des données d'une entreprise sur demande (pas self-service V1, manuel) |
| **Opposition** | Non applicable (base légale = exécution contractuelle + obligation légale) |

### Sous-traitance Hostinger

Le client archive le DPA Hostinger (Data Processing Agreement) reçu à la souscription du plan Business. Ce DPA couvre :
- Stockage des données en UE (vérifié à la souscription).
- Confidentialité, sécurité physique et logique du datacenter.
- Procédure de notification d'incident dans les 72 h.

### Notification d'incident

En cas de violation de données (fuite, accès non autorisé, perte) :
1. Le prestataire notifie le client (responsable) sous 24 h ouvrées.
2. Le client notifie la CNIL sous 72 h (via portail CNIL) si violation à risque.
3. Journalisation obligatoire dans un registre des violations (tenu par le client).

### Mesures techniques V1

- HTTPS obligatoire en production (certificat Let's Encrypt via Hostinger).
- Mots de passe hashés `bcrypt` (défaut Laravel 13).
- Sessions Laravel en base (driver `database` — cf. ADR-0008).
- Soft-delete systématique avec horodatage (`deleted_at`) — suppression physique uniquement par tâche de purge après 10 ans.
- Pas de données personnelles en dur dans les logs applicatifs (contrôle manuel + CI lint).

---

## Alternatives écartées

1. **Anonymisation après 2-3 ans** — incompatible avec le besoin de rejouer un calcul fiscal pour contrôle sur 10 ans (CGI art. L. 102 B).
2. **Hébergement CH/UK** — pas de bénéfice vs Hostinger EU (RGPD EU couvre les besoins), coût supérieur.
3. **Chiffrement applicatif des PII en base** — surcoût d'implémentation non justifié par le niveau de risque V1. À rouvrir en V2 si audit externe le demande.

---

## Conséquences

- Une **politique de confidentialité** conforme CNIL doit être rédigée et signée entre le client (responsable) et le prestataire (sous-traitant) avant mise en production.
- Une **page « Mentions légales et RGPD »** (hors UI Kit, cf. décision 2026-04-24) doit être accessible depuis l'interface connectée de Floty avant la livraison V1.
- Une **tâche Artisan de purge** `floty:rgpd:purge-expired` est à implémenter en phase 13 (effacement physique des enregistrements soft-deleted depuis > 10 ans, et des attributions/indisponibilités hors exercices conservés).
- Le **DPA Hostinger** doit être archivé par le client avant le déploiement V1.
- Les **logs de canal fiscal** sont conservés 10 ans (vs 90 j pour les autres logs) : configuration spécifique dans `config/logging.php`.

---

## Références

- ADR-0003 (PDF snapshots immuables — conservation 10 ans)
- ADR-0008 (stack technique V1 — Hostinger)
- CGI art. L. 102 B (obligation comptable 10 ans)
- CNIL — Guide RGPD pour TPE/PME
- `rapport-001.md` P1.1 (justification de cet ADR)

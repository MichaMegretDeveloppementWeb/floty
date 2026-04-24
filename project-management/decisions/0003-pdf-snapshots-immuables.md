# ADR-0003 — PDF de déclarations et snapshots immuables

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Décidée initialement** : 20 avril 2026 (brainstorming de cadrage)
> **Formalisée** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Floty produit des documents PDF récapitulatifs des taxes fiscales dues par chaque entreprise utilisatrice pour chaque année civile. Ces documents ont une portée juridique significative : ils servent de **pièce justificative** annexée aux déclarations fiscales officielles transmises à l'administration (formulaires 3310-A-SD ou 3517 selon le régime d'imposition de l'entreprise).

L'application permet, par conception, de **modifier a posteriori** des éléments qui influent sur les calculs déjà déclarés : une attribution peut être ajoutée, supprimée ou modifiée ; une règle fiscale peut être corrigée par seeder ; les caractéristiques d'un véhicule peuvent être ajustées après coup. Cette flexibilité soulève une question fondamentale :

**Que se passe-t-il du point de vue fiscal et juridique pour une déclaration déjà produite et transmise au fisc, quand les données qui l'ont engendrée évoluent ?**

Deux approches s'opposent :

1. **Régénérer la déclaration** à chaque consultation, en écrasant le PDF précédent — garantissant que le PDF reflète toujours l'état actuel des données.
2. **Figer la déclaration** au moment de sa génération, en conservant le PDF historique immuable — garantissant une trace exacte de ce qui a été transmis au fisc, mais laissant diverger le PDF de l'état courant.

## Décision

**Chaque PDF de déclaration est immuable, accompagné d'un snapshot immuable de toutes les données utilisées pour son calcul.** La régénération ultérieure produit un nouveau PDF, jamais un écrasement.

Concrètement :

- Chaque génération de déclaration crée un **PDF figé** (stocké physiquement en base ou en système de fichiers versionné).
- En parallèle, un **snapshot JSON** est persisté, capturant :
  - Toutes les attributions prises en compte
  - Les caractéristiques fiscales des véhicules à la date du calcul (en tenant compte de l'historisation — cf. cahier des charges § 2.1)
  - Les règles fiscales appliquées (identifiants + version)
  - Les données de l'entreprise utilisatrice (raison sociale, SIREN, adresse)
  - Les résultats intermédiaires et finaux (tarifs, prorata, ventilation par couple, totaux)
- Le PDF et son snapshot sont liés à l'enregistrement de la déclaration et ne peuvent pas être modifiés après création.
- Une régénération produit une **nouvelle ligne** dans l'historique des PDF de la déclaration, avec un nouveau PDF et un nouveau snapshot. Les versions antérieures restent accessibles.

L'application affiche l'historique des PDF générés pour chaque déclaration, du plus récent au plus ancien, avec date de génération et mention « à jour » ou « périmé » (cf. ADR-0004 sur l'invalidation par marquage).

## Justification

### Obligation de traçabilité fiscale

Quand une entreprise transmet à l'administration fiscale un document justificatif, elle engage sa responsabilité sur le contenu exact de ce document. En cas de contrôle ultérieur (parfois plusieurs années après), le contribuable doit pouvoir **exhiber le document tel qu'il a été transmis**, même si les données source ont évolué entretemps. Écraser un PDF déjà transmis rendrait impossible cette restitution.

### Auditabilité par l'expert-comptable

Un expert-comptable qui audite les déclarations fiscales de ses clients doit pouvoir **rejouer le calcul** exactement comme il a été produit à un instant donné. Le snapshot JSON permet cette reproductibilité stricte : les règles, les données, les caractéristiques sont toutes figées.

### Non-blocage des modifications

L'application est conçue pour être **non-bloquante** : rien n'empêche l'utilisateur de modifier une attribution passée ou une règle fiscale. L'immutabilité des PDF permet de concilier cette liberté de modification avec la stabilité des documents historiques.

### Détection d'invalidation

En comparant le snapshot d'un PDF avec l'état actuel des données, l'application peut détecter qu'une déclaration nécessite une régénération (cf. ADR-0004). Sans snapshot, cette détection serait impossible.

### Conformité à l'obligation de conservation

L'administration fiscale impose une conservation des pièces justificatives sur plusieurs années (généralement 10 ans). Conserver les PDF historiques alignés sur cette obligation est directement satisfait par le choix d'immutabilité.

## Alternatives écartées

### Alternative 1 — Régénération systématique à la consultation

Approche la plus simple conceptuellement : à chaque ouverture d'une déclaration, recalculer et régénérer le PDF.

**Rejetée** pour les raisons suivantes :

- Destruction irrémédiable des PDF transmis au fisc : impossibilité de justifier en cas de contrôle.
- Incohérence entre ce qui a été transmis (à une date T) et ce qui est affiché (à une date T+1).
- Régénérations inutiles coûteuses en performance.

### Alternative 2 — Régénération sur demande avec écrasement

Variante partielle : le PDF est régénéré uniquement sur demande explicite de l'utilisateur, mais l'opération écrase l'ancien.

**Rejetée** pour la même raison principale : perte de l'historique de ce qui a été transmis.

### Alternative 3 — Versioning minimal sans snapshot des données

Approche où l'on conserve les PDF historiques mais sans capturer le snapshot des données source.

**Rejetée** pour les raisons suivantes :

- Impossibilité de rejouer un calcul à l'identique pour audit.
- Détection d'invalidation impossible (on ne peut pas comparer le snapshot au présent).
- Risque de trou de traçabilité en cas de corruption d'un PDF ou d'un problème technique.

## Conséquences

### Conséquences positives

- **Traçabilité fiscale complète** : chaque déclaration transmise au fisc a une preuve matérielle persistante.
- **Auditabilité** : un expert-comptable peut rejouer exactement un calcul historique à partir du snapshot.
- **Non-blocage** : l'utilisateur peut modifier librement les données actuelles sans craindre de perdre l'historique.
- **Détection d'invalidation** : le snapshot permet la comparaison avec l'état courant (cf. ADR-0004).

### Conséquences techniques

- Une table `declaration_pdfs` (ou équivalent) stocke les PDF historiques avec leur date de génération et leur snapshot JSON.
- Le schéma JSON du snapshot doit être stable et versionné (son évolution doit permettre de lire les snapshots anciens).
- Les PDF sont stockés sur filesystem local (disk `local` de Laravel, chemin dans `storage/app/private/declarations/...`) avec un pointeur stocké en base dans `declaration_pdfs.file_path`. **Décision tranchée par ADR-0008** (stack technique V1, hébergement Hostinger mutualisé sans stockage objet disponible).
- Un effort modéré de stockage : pour une flotte de 100 véhicules et 30 entreprises, une déclaration annuelle pèse probablement quelques centaines de Ko ; multiplié par le nombre de régénérations et d'années, cela reste gérable.

### Conséquences produit

- La vue « Déclarations » affiche pour chaque déclaration la liste des PDF générés, chronologique du plus récent au plus ancien.
- Un PDF périmé (invalidé) affiche un badge visuel (cf. ADR-0004) mais reste téléchargeable tel quel.
- Régénérer une déclaration n'écrase jamais l'historique ; c'est un ajout.

### Conséquences métier

- Le client doit accepter une légère consommation de stockage (plusieurs versions par déclaration) en échange de la traçabilité. Cette consommation reste faible en valeur absolue et est un coût négligeable face à la valeur apportée.

### Obligations opérationnelles

- Les seeders (cf. ADR-0002) doivent **versionner** les règles : quand une règle est corrigée, elle passe à une nouvelle version interne, et les snapshots historiques référencent la version qui était en vigueur au moment de la génération.
- La page de consultation des règles (cf. ADR-0001) doit pouvoir afficher, pour un PDF historique, la version des règles qui était appliquée.

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0002 — Règles non éditables depuis l'application en V1
- ADR-0004 — Invalidation de déclarations par marquage
- ADR-0006 — Architecture du moteur de règles
- `project-management/cahier_des_charges.md` § 3.11 (Fiscalité — Déclarations) et § 5.7 (Récapitulatif fiscal)

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation d'une décision prise dès le brainstorming de cadrage (20/04/2026). |

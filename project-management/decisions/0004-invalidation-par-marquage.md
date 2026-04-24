# ADR-0004 — Invalidation de déclarations par marquage (non-blocante)

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Décidée initialement** : 20 avril 2026 (brainstorming de cadrage)
> **Formalisée** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

L'ADR-0003 a acté l'immutabilité des PDF de déclarations et de leurs snapshots. Une conséquence directe se pose : **comment signaler à l'utilisateur qu'une déclaration déjà générée ne reflète plus l'état actuel des données ?**

Le cas d'usage type : Renaud génère le PDF récapitulatif 2024 pour l'entreprise ACME en janvier 2025, le transmet au fisc. Trois mois plus tard, il découvre qu'il a oublié de saisir une indisponibilité de 15 jours sur un véhicule, ou il reçoit une correction de règle fiscale de son expert-comptable que nous devons déployer par seeder. Les données sous-jacentes de la déclaration ACME 2024 ont changé. Le PDF existant est désormais **périmé** — le calcul qu'il contient ne correspond plus au calcul qui serait produit aujourd'hui avec les mêmes données et règles.

Trois grandes familles de comportement étaient envisageables :

1. **Bloquer les modifications** qui pourraient invalider une déclaration déjà produite (par exemple : interdire la modification d'attributions passées une fois le PDF généré).
2. **Régénérer automatiquement** les déclarations en arrière-plan dès qu'une modification affecte leur validité.
3. **Marquer** les déclarations comme « régénération requise » en les laissant telles quelles et en signalant visuellement à l'utilisateur.

## Décision

**Floty adopte le mécanisme de marquage (option 3) : non-blocant, non-régénératif automatique, purement informatif.**

Concrètement :

- L'application **n'interdit aucune modification** susceptible d'invalider une déclaration existante (modification d'attribution, ajout d'indisponibilité, déploiement d'un correctif de règle, etc.).
- Chaque déclaration portant un PDF généré est comparée **à la consultation** (ou plus efficacement, à la modification déclenchant l'invalidation) avec son snapshot : si divergence → la déclaration est marquée « régénération requise ».
- Le **statut principal** de la déclaration (Brouillon / Vérifiée / Générée / Envoyée — cf. cahier des charges § 3.11) **n'est pas modifié** par l'invalidation. Une déclaration « Envoyée » reste « Envoyée » même si ses données sous-jacentes ont changé.
- Un **indicateur visuel distinct** signale l'état d'invalidation : badge orange « Régénération requise », accompagné d'une liste lisible des éléments qui ont changé depuis la dernière génération.
- L'utilisateur choisit quand et si régénérer. Une régénération produit un **nouveau** PDF (cf. ADR-0003 — immutabilité), n'écrase pas l'ancien, et retire le marquage d'invalidation sur la déclaration.

## Justification

### Liberté de correction sans friction

Interdire les modifications aurait produit une ergonomie lourde et déresponsabilisante : l'utilisateur aurait dû gérer préalablement un « déverrouillage » explicite avant chaque correction. Inadapté à un outil où la saisie peut nécessiter des ajustements fréquents (oublis, erreurs, indisponibilités déclarées tardivement).

### Refus de la régénération automatique opaque

Régénérer automatiquement en arrière-plan aurait posé plusieurs problèmes :

- **Trace d'archivage perdue** : en silence, le PDF « ancien » aurait été remplacé, contrevenant à l'obligation de traçabilité fiscale (cf. ADR-0003).
- **Calculs fantôme** : un PDF régénéré sans que l'utilisateur le sache pourrait refléter une correction qu'il n'a pas validée.
- **Charge serveur** : chaque modification déclenchant une régénération complète produit des calculs potentiellement coûteux sans valeur ajoutée immédiate.

### Séparation claire entre statut et validité

Le statut (Brouillon / Vérifiée / Générée / Envoyée) reflète le **parcours d'usage** : où en est la déclaration dans le cycle de vie métier ? L'invalidation reflète la **cohérence entre le PDF figé et l'état courant des données**. Ces deux dimensions sont orthogonales : une déclaration peut être « Envoyée » et « Régénération requise » simultanément, et c'est une information utile (il faudra probablement transmettre un PDF corrigé au fisc).

### Transparence et contrôle utilisateur

En affichant explicitement « X modifications depuis la dernière génération », l'utilisateur garde la main. Il peut évaluer si les modifications justifient une nouvelle transmission au fisc, ou si elles sont mineures et peuvent attendre.

## Alternatives écartées

### Alternative 1 — Blocage strict des modifications

Interdire toute modification de données influant sur une déclaration déjà générée, sauf déverrouillage préalable avec confirmation.

**Rejetée** pour les raisons suivantes :

- Ergonomie lourde, contraire à l'esprit de Floty (saisie rapide, corrections fréquentes).
- Ne résout pas le cas des déploiements de correctifs de règles (non déclenchés par l'utilisateur).
- Risque que l'utilisateur contourne le blocage en « dégénérant » le PDF juste pour modifier, puis régénérant — complexité sans gain.

### Alternative 2 — Régénération automatique silencieuse

Toute modification de données affectant une déclaration déclenche immédiatement la régénération de son PDF en arrière-plan.

**Rejetée** pour les raisons suivantes :

- Contraire à l'immutabilité des PDF (ADR-0003).
- Perte de traçabilité du document effectivement transmis au fisc.
- Charge serveur disproportionnée par rapport à la valeur fonctionnelle.
- Cache la réalité à l'utilisateur, qui pourrait ignorer qu'une modification récente a changé la taxe due.

### Alternative 3 — Audit trail granulaire des modifications

Approche plus riche que le simple marquage : conserver la trace détaillée de chaque modification (qui, quand, ancien état, nouvel état), exposée via un historique consultable.

**Repoussée** (pas « rejetée ») **pour la V1** : cette fonctionnalité a été identifiée comme non-prioritaire (cf. décision prise dans les premières passes de brainstorming, avant la phase de recherche fiscale). Elle est prévue pour une évolution future (V2+), mais pas indispensable pour le fonctionnement de l'application V1. Le snapshot JSON déjà attaché à chaque PDF (ADR-0003) fournit de fait une forme d'audit trail rétrospectif suffisant pour les besoins de traçabilité fiscale.

## Conséquences

### Conséquences positives

- **Liberté de modification** préservée, cohérente avec l'esprit produit.
- **Immutabilité des PDF** respectée, conformément à ADR-0003.
- **Contrôle utilisateur** : il décide quand et si régénérer.
- **Visibilité claire** : le badge orange rend l'invalidation immédiatement perceptible sur la vue Déclarations.

### Conséquences techniques

- Un mécanisme de **détection d'invalidation** doit être implémenté. Deux approches possibles :
  - **À l'action** : liste déclarée d'actions susceptibles d'invalider (modification d'attribution, ajout d'indisponibilité, déploiement de règle, modification de caractéristique véhicule fiscale, modification d'entreprise). Chaque action déclenche une vérification immédiate.
  - **À la consultation** : comparaison entre snapshot et état courant à chaque ouverture de la modal détail déclaration.
  
  **Préconisation V1** : combinaison des deux. La détection à l'action est plus réactive (le marquage apparaît immédiatement) ; la détection à la consultation sert de filet de sécurité en cas d'oubli dans la liste des actions invalidantes.

- Un **journal léger** peut être tenu pour chaque déclaration invalidée : « Invalidée le 12 mars 2026 à 14:32 suite à : modification d'attribution sur véhicule AB-123-CD / déploiement de correctif règle R-2024-017 / etc. » Ce journal n'a pas vocation à être un audit trail complet (cf. alternative 3 reportée) mais à donner à l'utilisateur un minimum de contexte.

- La comparaison snapshot vs état courant doit être **rapide** : par exemple un hash signature sur les données clés (hash des attributions + hash des caractéristiques véhicule + hash de la version des règles), sauvegardé avec le snapshot, comparé à chaque consultation.

### Conséquences produit

- Sur la vue Déclarations (cf. cahier des charges § 3.11), chaque déclaration affiche :
  - Son statut principal (Brouillon / Vérifiée / Générée / Envoyée)
  - Un indicateur visuel secondaire si invalidée : badge orange, mention « Régénération requise »
  - Le nombre et la nature des modifications depuis la dernière génération, en résumé
- Dans la modal détail déclaration, un bouton « Régénérer » est prééminent si l'invalidation est active.
- L'historique des PDF (ADR-0003) reste accessible pour les régénérations passées.

### Conséquences UX

- L'utilisateur apprend à distinguer « statut de cycle de vie » (ce que la déclaration représente dans le parcours métier) et « statut de validité » (cohérence courante avec les données).
- La régénération doit être une action **rapide et fiable** : un clic, un nouveau PDF disponible en quelques secondes.

### Limites assumées

- Si une modification est mineure (par exemple correction d'une faute de frappe dans la raison sociale de l'entreprise), l'utilisateur doit juger par lui-même si une régénération et une nouvelle transmission au fisc sont nécessaires. Floty n'automatise pas ce jugement.
- Si l'utilisateur ignore le marquage et ne régénère jamais, les écarts s'accumulent silencieusement. C'est son choix, non notre responsabilité ; l'information a été rendue visible.

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0003 — PDF et snapshots immuables des déclarations
- ADR-0006 — Architecture du moteur de règles
- `project-management/cahier_des_charges.md` § 3.11 (Fiscalité — Déclarations)

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation d'une décision prise dès le brainstorming de cadrage (20/04/2026). |

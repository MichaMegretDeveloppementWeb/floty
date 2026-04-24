# ADR-0001 — La fiscalité est une donnée, pas du code

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Décidée initialement** : 20 avril 2026 (brainstorming de cadrage)
> **Formalisée** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

L'application Floty a pour cœur métier le calcul de taxes fiscales françaises applicables aux véhicules d'entreprise (taxe CO₂, taxe polluants atmosphériques, exonérations, abattements). Ces règles fiscales sont nombreuses, évoluent dans le temps (révision annuelle des barèmes, changements législatifs, ajustements réglementaires), et leur interprétation peut nécessiter des corrections ponctuelles (retours expert-comptable, publication ultérieure de doctrine BOFiP).

Au démarrage du projet, une décision fondamentale devait être prise : **comment incarner ces règles fiscales dans l'application ?**

Deux approches étaient possibles :

1. **Les règles hardcodées dans le code métier du calcul de taxes** — chaque taxe, chaque exonération, chaque barème écrit directement dans les fonctions de calcul, sans abstraction particulière.
2. **Les règles traitées comme des données de première classe** — chaque règle modélisée comme une entité, stockée en base avec ses métadonnées, interprétée par un moteur générique.

## Décision

**Les règles fiscales sont traitées comme des données de première classe.** L'application Floty n'est pas un calculateur ad hoc qui « sait » calculer la taxe CO₂ 2024 ; c'est un **moteur générique** qui **interprète** un catalogue de règles fiscales stockées en base, versionnées, et consultables.

Concrètement :

- Chaque règle fiscale existe en tant qu'entité identifiable (id, libellé, période d'application, base légale, paramètres, etc.).
- Le moteur de calcul applique les règles applicables à un contexte donné (véhicule, entreprise utilisatrice, année) sans connaissance préalable de leur contenu sémantique.
- Une page dédiée de l'application (Fiscalité → Règles de calcul) permet à l'utilisateur de **consulter** les règles en vigueur pour chaque année fiscale, dans un rendu lisible.
- Les règles évoluent par ajout ou modification dans le catalogue, sans réécriture du moteur.

## Justification

### Auditabilité fiscale

Les déclarations produites par Floty sont des pièces justificatives fiscales. En cas de contrôle par l'administration ou d'audit par un expert-comptable, la capacité à **exhiber les règles appliquées** pour chaque calcul est essentielle. Un moteur-boîte-noire rendrait cette exhibition laborieuse ; un catalogue de règles la rend triviale.

### Évolutivité

Les règles fiscales évoluent chaque année (loi de finances, révision des barèmes, abrogation de dispositifs). Séparer les règles du moteur permet de faire évoluer les unes sans toucher à l'autre. Un seeder déployé annuellement suffit à mettre à jour le catalogue pour une nouvelle année fiscale — sans régression possible sur les années antérieures.

### Capacité de correction

Si une règle s'avère mal interprétée après livraison (retour expert-comptable, publication BOFiP ultérieure), la correction est localisée : un seul enregistrement du catalogue à amender. Le moteur continue de fonctionner sans modification.

### Transparence client

Le client (et son expert-comptable) peut à tout moment consulter « la règle appliquée pour la taxe CO₂ d'un véhicule WLTP en 2024 » et la lire en français, avec référence au texte CIBS. Cette transparence est au cœur de la valeur que Floty apporte au-delà du simple calcul.

### Durabilité pluriannuelle

Floty est conçu pour fonctionner sur plusieurs années fiscales simultanément (calcul 2024, 2025, 2026…). Avec des règles hardcodées, chaque nouvelle année imposerait une réécriture de la logique. Avec un catalogue, chaque nouvelle année imposerait simplement un ajout au catalogue.

## Alternatives écartées

### Alternative 1 — Règles hardcodées dans le code métier

Approche la plus directe : chaque taxe implémentée comme une fonction dédiée, chaque barème comme un tableau constant dans le code, chaque exonération comme une série de conditions `if/else`.

**Rejetée** pour les raisons suivantes :

- **Auditabilité dégradée** : impossible pour un tiers (expert-comptable, contrôleur fiscal) d'extraire la règle appliquée sans lire le code source.
- **Évolution coûteuse** : chaque révision annuelle nécessite une réécriture du code métier et une passe de tests complète.
- **Couplage fort** : moteur et règles ne font qu'un ; impossible de corriger une règle sans redéployer l'ensemble.
- **Transparence impossible** pour le client : on ne peut pas exhiber le code source comme preuve fiscale.

### Alternative 2 — DSL d'expression en base (formules textuelles évaluées)

Approche plus data-centric : chaque règle stockée en base comme une chaîne d'expression mathématique (ex : `si co2_wltp > 50 alors ...`) évaluée à la volée.

**Rejetée** pour les raisons suivantes :

- **Complexité du DSL** : les règles fiscales réelles comportent des cas conditionnels complexes (cumul annuel par couple pour la LCD, aménagements transitoires, interactions entre règles) difficiles à exprimer dans un DSL sans réinventer un langage de programmation complet.
- **Impossibilité des tests unitaires** : un DSL en base ne peut pas être testé avec les outils standards de développement.
- **Fragilité** : une erreur de syntaxe dans une formule stockée en base n'est détectée qu'à l'évaluation, potentiellement en production.
- **Non-pertinent fonctionnellement** : puisque les règles ne sont éditables que par l'équipe technique (cf. ADR-0002), il n'y a aucun gain à les exprimer dans un DSL stocké en base. Autant les exprimer en code natif, testé et typé.

## Conséquences

### Conséquences positives

- **Moteur générique** : un seul mécanisme d'orchestration couvre toutes les règles (existantes et futures).
- **Page de consultation immédiate** : la liste des règles applicables à une année est une requête simple en base.
- **Versioning possible** : chaque règle peut porter une version ; les snapshots de déclarations (cf. ADR-0003) peuvent référencer une version précise.
- **Évolution pluriannuelle fluide** : ajouter l'année 2025 consiste à ajouter les règles 2025 au catalogue, sans toucher au moteur.
- **Analyse d'impact** : une requête « quelles règles utilisent la caractéristique X ? » est triviale quand les règles sont des données.

### Conséquences techniques

- Le moteur de calcul doit être conçu avec un **pipeline d'orchestration** générique (cf. ADR-0006).
- Les règles doivent exposer une **interface commune** permettant au moteur de les invoquer sans connaissance de leur contenu sémantique (cf. ADR-0006).
- Une table `fiscal_rules` en base porte les métadonnées de chaque règle (cf. ADR-0002 et ADR-0006).
- Le code source des règles (leur logique concrète) reste en code versionné git, relié à chaque enregistrement de la table par un champ `code_reference` (cf. ADR-0002).

### Conséquences organisationnelles

- L'équipe technique (prestataire) devient l'unique responsable de la qualité des règles codées. Cela impose un processus rigoureux de recherche fiscale documentée (cf. `project-management/recherches-fiscales/methodologie.md`), de production de règles consolidées (`taxes-rules/{année}.md`), et de seeders correctement déployés.
- Le client (Renaud + son expert-comptable) est dans une **posture de validation** : il consulte les règles dans Floty et/ou dans la documentation, signale des anomalies, nous les corrigeons.

### Conséquences produit

- L'application propose une page « Règles de calcul » consultable par l'utilisateur, indispensable pour la confiance dans le calcul.
- Les règles ne sont pas un élément de complexité pour l'utilisateur : elles sont **données**, consultables, transparentes.

## Liens

- `project-management/recherches-fiscales/methodologie.md` — cadre de production des règles
- `project-management/taxes-rules/2024.md` — catalogue consolidé des règles 2024 (24 règles)
- ADR-0002 — Règles non-éditables depuis l'application en V1
- ADR-0006 — Architecture du moteur de règles

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation d'une décision prise dès le brainstorming de cadrage (20/04/2026) et appliquée de manière cohérente tout au long de la recherche fiscale 2024. |

# ADR-0007 — Périmètre fonctionnel V1 (MVP)

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Après la phase de recherche fiscale exhaustive (catalogue de 24 règles pour l'exercice 2024, cf. `project-management/taxes-rules/2024.md`) et la conception de l'architecture du moteur de règles (ADR-0006), il était nécessaire de cadrer précisément **quelles fonctionnalités du cahier des charges entrent dans la première version livrée au client**, et lesquelles sont reportées à des versions ultérieures.

Le cahier des charges (`project-management/cahier_des_charges.md` § 3 Vues et interface, § 4 Fonctionnalités transverses) décrit un produit riche. Réaliser l'ensemble en un seul livrable initial serait irréaliste, et risquerait de produire une V1 large mais bancale plutôt qu'une V1 ciblée mais solide.

La décision a été prise après échange entre le prestataire et le client (Renaud), en tenant compte de trois critères :

1. **Essentialité à la valeur fiscale** : la fonctionnalité est-elle indispensable pour produire une déclaration fiscale juste ?
2. **Essentialité à la validation client** : sans elle, le client peut-il tester l'outil en conditions réelles et se prononcer sur son adoption ?
3. **Rapport coût / valeur** : effort de développement comparé à la valeur apportée en V1.

Une fonctionnalité cochant au moins l'un des deux premiers critères entre en V1. Les autres sont positionnées sur V1.2, V2 ou V3.

## Décision

### Périmètre V1 (inclus dans le livrable initial)

**Authentification et gestion utilisateurs**
- Connexion email + mot de passe simple.
- Création de comptes exclusivement par seeder côté équipe technique (pas d'invite email auto, pas de « créer un compte » en libre-service).
- Pas de mécanisme « mot de passe oublié » en libre-service (reset manuel à la demande du client, cf. ADR-0002 pour l'esprit minimaliste V1).
- Tous les comptes ont les mêmes droits (pas de gestion de rôles en V1).

**Entités métier (CRUD complet)**
- **Véhicules** avec historisation des caractéristiques fiscales (cf. cahier des charges § 2.1 v1.4 : champs identification administrative, caractéristiques techniques fiscalement déterminantes, champs conditionnels) et traçabilité des versions (cf. ADR-0006 § 7 temporalité).
- **Entreprises utilisatrices**.
- **Conducteurs** (entité conservée, usage secondaire — sans fonctionnalité « Remplacer par… », qualifiée par décision antérieure).

**Planning et saisie d'attribution**
- **Saisie hebdomadaire « mode tableur »** (cahier des charges § 3.6) : grille véhicules × 7 jours, code court entreprise au clavier, sélection multi-cellules, duplication, attribution des jours libres. Outil quotidien principal.
- **Wizard d'attribution rapide** (cahier des charges § 3.8) : modal 3 étapes (qui / quand / quel véhicule) pour les cas ponctuels.
- **Indisponibilités** avec saisie, affichage planning, et impact fiscal correct : seul le type « Fourrière / immobilisation administrative » réduit le prorata fiscal (cahier des charges § 2.5 v1.4).

**Vues planning**
- **Heatmap globale annuelle** (cahier des charges § 3.3) — vue maîtresse pour la vision d'ensemble. 100 véhicules × 52 semaines, rendu performant (< 200 ms), filtrage par type / carburant / statut.
- **Vue par entreprise** (cahier des charges § 3.4 v1.4) — avec **compteur LCD par couple véhicule × entreprise** (cumul annuel des jours + impact fiscal estimé en temps réel, conformément à R-2024-021 du catalogue).
- **Vue par véhicule** (cahier des charges § 3.5) — fiche détaillée avec planning segmenté par couleur d'entreprise + tableau de répartition fiscale.
- **Panneau latéral de détail semaine** (cahier des charges § 3.7) — drawer au clic sur une cellule.
- **Sélecteur d'année** persistant en top bar.

**Calcul fiscal et déclarations**
- **Moteur de règles** complet conformément à l'ADR-0006, avec les 24 règles 2024 du catalogue.
- Codage **de toutes les exonérations du catalogue 2024**, y compris celles marquées « inactives par défaut » (R-2024-018 organismes d'intérêt général, R-2024-019 entreprises individuelles, R-2024-022 exonérations à activité). Justification : coût d'implémentation marginal, aucune régression à prévoir si une entreprise utilisatrice change de profil, et démonstration de l'exhaustivité de la recherche fiscale vis-à-vis du client.
- **Page « Fiscalité → Règles de calcul »** en lecture seule (ADR-0002, cahier des charges § 3.13).
- **Page « Fiscalité → Déclarations »** (cahier des charges § 3.11) : liste par entreprise × année, statuts (Brouillon / Vérifiée / Générée / Envoyée), badge d'invalidation (ADR-0004), historique des PDF générés par déclaration (ADR-0003).
- **Génération PDF récapitulatif fiscal** (cahier des charges § 5.7) — seul PDF produit en V1. Snapshot immuable (ADR-0003).

**Application de base**
- Sidebar permanente pour la navigation entre sections.
- Top bar permanente avec sélecteur d'année et bouton « Nouvelle attribution ».
- **Barre de recherche globale** en version basique : recherche texte simple (filtrage) dans véhicules (immat, modèle), entreprises (nom, SIREN), conducteurs. Pas de raccourci ⌘K avec IA en V1.
- **Dashboard** (cahier des charges § 3.2) en version simple : KPI calculés directement (taux d'occupation, véhicules actifs, entreprises actives, estimation taxes année en cours). **Pas d'alertes intelligentes ni d'IA** en V1.
- **Hébergement** Hostinger Paris (décision antérieure).
- **Page d'accueil publique minimale** + **page mentions légales** + **page de connexion**. Trame CNIL-conforme pour les mentions légales, rédigée par nous et complétée par les informations spécifiques de Renaud (raison sociale, SIREN, DPO le cas échéant).
- **Design responsive** : desktop prioritaire, viable sur tablette. Les vues heatmap annuelles ne peuvent pas être parfaitement adaptées à 380 px de large — accepté par le client (cahier des charges § 1.4).

### Périmètre V1.2 (ajout programmé entre V1 et V2)

**Module facturation loyers**
- Grilles tarifaires de location (journalier / mensuel) configurables par la société de location.
- Calcul automatique des loyers dus par chaque entreprise utilisatrice sur la base des attributions déjà enregistrées dans Floty.
- **Génération de PDF « facture aux entreprises utilisatrices »** — distinct du PDF récapitulatif fiscal. Ce PDF représente le **montant commercial** que la société de location facture à chaque entreprise utilisatrice pour la mise à disposition des véhicules (pas un document fiscal destiné à l'administration).
- Envoi des factures aux entreprises utilisatrices.
- Cette fonctionnalité ne modifie pas le modèle fiscal mais ajoute un flux parallèle sur les mêmes données d'attribution (cf. mémoire projet `roadmap_v12_facturation.md`).

### Périmètre V2 (évolutions fonctionnelles envisagées)

Positionnées comme **reportées** à V2 — la priorisation précise se fera après livraison V1 et retours d'usage.

- **Analytics multi-années** (cahier des charges § 3.12) : comparaison inter-années, détection de variations, graphiques, filtres.
- **Alertes sur le dashboard** (véhicules sous-utilisés, CT à prévoir, conducteurs inactifs, exonérations applicables) — logique de seuils et d'analyse, sans IA nécessairement.
- **Audit trail complet des modifications d'attribution** (auteur, date, ancien/nouvel état). Le snapshot des PDF (ADR-0003) fournit déjà une forme d'audit partiel ; un audit trail complet serait une extension.
- **Récurrence des attributions** (copier un pattern de semaine sur N semaines).
- **Gestion de rôles et permissions** (plusieurs comptes avec droits différenciés).
- **Formulaire officiel pré-rempli** type 3310-A-SD ou 3517 en plus du récapitulatif.
- **Notifications par email** (déclarations à générer, alertes).
- **Mode multi-société** (la société de location comme entité gérée avec son propre reporting, au lieu d'être implicite).

### Périmètre V3 (sophistications et IA)

- **Détection d'anomalies par IA** sur les patterns d'utilisation (véhicules sous-utilisés selon seuils adaptatifs, changements brutaux de profil, incohérences de saisie).
- **Suggestions d'attribution IA** dans le wizard (recommandation optimale basée sur utilisation récente, taux d'occupation, optimisation LCD).
- **Analyse fiscale prédictive** : projection des taxes, identification des remplacements les plus rentables, simulation d'impacts.
- **Recherche en langage naturel** (⌘K avec NLP : « quels véhicules sont libres la semaine prochaine ? »).
- **Auto-complétion depuis API SIV** pour pré-remplir les caractéristiques techniques d'un véhicule à partir de son immatriculation.
- **Application mobile dédiée**.
- **Intégration comptable** (export vers logiciels comptables).
- **Notifications push** (en complément ou remplacement des emails V2).

### Exclusions durables (non planifiées)

- **Import CSV / Excel** : exclu du périmètre Floty de manière durable, indépendamment de la version. Raison : Renaud a explicitement confirmé que les données historiques dont il dispose sont inexploitables (« ils ont fait n'importe quoi, c'est d'ailleurs pour cela que je commande cette application, car la méthode actuelle favorise trop les erreurs de saisie »). Il n'y a donc pas de source de données existante qui justifierait de développer un import. Si à terme Floty était proposé à d'autres clients disposant de données propres, la décision pourrait être revue.
- **Gestion de la TAI (Taxe annuelle incitative au verdissement)** : hors périmètre Floty de manière définitive. La TAI est applicable aux propriétaires de flotte ≥ 100 véhicules, or Floty calcule uniquement les taxes dues par les entreprises utilisatrices (qui, prises individuellement, n'atteignent jamais ce seuil). Décision actée dès la cartographie phase 0.
- **PDF fiche véhicule individuelle** et **PDF planning** : non prévus en V1 ni en V2. Seul le PDF récapitulatif fiscal (V1) et le PDF facture aux entreprises utilisatrices (V1.2) sont dans la roadmap. Les autres exports PDF n'ont pas démontré de valeur client justifiant le développement.

## Justification

### Pourquoi ce découpage

Le découpage V1 / V1.2 / V2 / V3 suit la hiérarchie de valeur pour le client :

- **V1** = cœur fiscal complet et opérationnel. Ce qui permet à Renaud de produire les déclarations fiscales justes pour ses entreprises utilisatrices. C'est la raison d'être de Floty.
- **V1.2** = extension commerciale. Ce qui permet à Renaud de gérer la facturation des loyers, fonctionnalité complémentaire déjà évoquée en amont et distincte du volet fiscal.
- **V2** = confort et reporting. Ce qui permet d'optimiser l'usage après appropriation du produit (analytics, alertes, récurrence, etc.).
- **V3** = sophistications IA. Ce qui donne à Floty une dimension avancée une fois le socle parfaitement stabilisé.

### Pourquoi pas d'import CSV

La décision d'exclure l'import CSV de manière durable s'appuie sur un diagnostic métier clair formulé par Renaud : les données historiques (saisies manuellement dans des tableurs depuis plusieurs années) comportent suffisamment d'erreurs pour que les charger dans Floty introduirait plus de problèmes qu'il n'en résoudrait. L'achat de Floty est précisément motivé par la volonté de repartir sur une saisie propre et contrôlée.

Le coût de la reprise manuelle est assumé par Renaud.

### Pourquoi coder toutes les exonérations, y compris les inactives par défaut

Deux arguments :

1. **Robustesse pour les évolutions** : si demain une entreprise utilisatrice du groupe change d'activité (acquiert un statut d'entreprise individuelle, ou bascule sur une activité agricole, etc.), la règle correspondante est déjà opérationnelle sans modification de Floty.
2. **Signal qualité vis-à-vis du client** : l'exhaustivité du catalogue implémenté, observable dans la page de consultation des règles, démontre la rigueur du travail de recherche fiscale et justifie le positionnement qualité du produit.

Le coût marginal d'implémentation (quelques heures par règle) est négligeable face à ces deux bénéfices.

## Alternatives écartées

### Alternative 1 — V1 plus restreinte (sans heatmap globale, sans wizard)

Livrer une V1 strictement minimale : saisie hebdomadaire uniquement, vues basiques, calcul fiscal. Reporter heatmap globale et wizard en V1.x.

**Rejetée** : la heatmap globale est la vue d'ensemble qui donne au produit sa lisibilité ; le wizard permet les saisies ponctuelles. Sans l'une ou l'autre, l'ergonomie serait trop dégradée pour que le client valide sérieusement le produit.

### Alternative 2 — V1 plus large (incluant analytics, alertes, audit trail)

Livrer une V1 qui couvre l'essentiel du cahier des charges hors IA.

**Rejetée** : délai et coût trop élevés pour une première livraison. Risque de V1 large mais instable, difficile à valider méthodologiquement. Mieux vaut livrer moins et bien que plus et mal.

### Alternative 3 — Import CSV minimal en V1

Développer un import limité (par exemple uniquement les attributions) pour permettre à Renaud de reprendre l'historique 2024 rapidement.

**Rejetée** : pas de données exploitables à importer. Décision client ferme.

## Conséquences

### Conséquences positives

- **V1 claire et complète sur son périmètre** : Renaud peut tester Floty de bout en bout sur une année fiscale et valider en conditions réelles.
- **Compréhension partagée** : le découpage V1 / V1.2 / V2 / V3 donne une visibilité long terme au client et à l'équipe technique.
- **Évolution maîtrisée** : chaque version apporte un ensemble cohérent de fonctionnalités, testables en conditions réelles avant de passer à la suivante.

### Conséquences techniques

- Le modèle de données (étape 4 du workflow projet) doit être conçu pour supporter dès V1 les exigences futures (facturation V1.2, analytics V2, etc.) — notamment en ne verrouillant pas le schéma sur des hypothèses uniquement V1.
- La stack technique (étape 5 du workflow) doit permettre une évolution modulaire (ajout du module facturation V1.2 sans refonte).
- Les migrations et seeders doivent être versionnés pour permettre l'ajout progressif de fonctionnalités.

### Conséquences produit

- La page d'accueil post-livraison V1 met en avant les fonctionnalités fiscales (calcul, planning, PDF), pas les fonctionnalités absentes.
- La documentation utilisateur V1 n'expose pas les fonctionnalités V2/V3 pour éviter la confusion.

### Conséquences commerciales

- La facturation client s'articule probablement en paliers : V1 livrée et facturée, V1.2 comme extension facturée séparément, V2 et V3 comme prestations ultérieures à négocier.
- La facturation annuelle (mise à jour des règles fiscales pour les années à venir) reste un flux récurrent indépendant des évolutions V2/V3, cf. ADR-0002.

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0002 — Règles non éditables depuis l'application en V1
- ADR-0003 — PDF et snapshots immuables des déclarations
- ADR-0004 — Invalidation de déclarations par marquage
- ADR-0005 — Calcul fiscal jour-par-jour
- ADR-0006 — Architecture du moteur de règles
- `project-management/roadmap.md` — vue d'ensemble navigable des versions
- `project-management/cahier_des_charges.md` § 3, § 4, § 9 — description détaillée des fonctionnalités

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — cadrage du périmètre V1 (MVP) après échange avec le client. Positionnement des fonctionnalités non retenues sur V1.2 / V2 / V3 / exclusions durables. |

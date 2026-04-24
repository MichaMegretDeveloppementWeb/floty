# Cahier des charges — Application de gestion de flotte partagée

> **Version** : 1.5  
> **Date initiale** : 20 avril 2026  
> **Dernière révision** : 23 avril 2026  
> **Client** : Renaud (société de location inter-sociétés)  
> **Prestataire** : Micha MEGRET — Développeur web freelance  
> **Nom de l'application** : [NOM APP] *(placeholder — nom définitif à déterminer)*

---

## 1. Contexte et objectif

### 1.1 Contexte métier

Le client est propriétaire d'une société de location de véhicules qui met sa flotte (environ 100 véhicules) à disposition d'une trentaine d'entreprises utilisatrices. Ces entreprises sont toutes détenues ou gérées par le même dirigeant — il s'agit d'un montage de groupe inter-sociétés, pas de location courte durée classique.

Chaque entreprise utilisatrice est fiscalement redevable des taxes annuelles sur les véhicules de tourisme affectés à des fins économiques (les deux taxes ayant remplacé la TVS depuis 2022), au prorata de son utilisation réelle de chaque véhicule.

Aujourd'hui, le suivi est géré manuellement (probablement via tableurs), ce qui rend la tenue des données pénible, les erreurs fréquentes, et la production des documents fiscaux longue et risquée.

### 1.2 Objectif de l'application

Construire un outil web qui permet à Renaud de :

1. **Planifier et suivre l'utilisation de chaque véhicule** au jour le jour, par entreprise et par conducteur
2. **Visualiser instantanément** l'état de sa flotte avec des vues annuelles denses et lisibles
3. **Calculer automatiquement et avec exactitude** les taxes applicables pour chaque entreprise, pour chaque véhicule, pour chaque année
4. **Générer des documents fiscaux récapitulatifs** (PDF) servant de pièces justificatives pour la déclaration aux services fiscaux
5. **Garder un historique complet** des véhicules, des entreprises, des conducteurs et des attributions sur plusieurs années (rétroactif jusqu'à 2024 minimum)

L'application est avant tout **une machine à produire des déclarations fiscales justes, avec le minimum de saisie possible en amont**. Les vues planning sont le moyen élégant et efficace de saisir la donnée source.

### 1.3 Utilisateurs

Au lancement, un utilisateur unique (Renaud). Cependant, l'architecture doit supporter nativement plusieurs utilisateurs (table users, authentification). Pas de gestion de rôles ni de permissions pour le moment — tous les utilisateurs ont les mêmes droits d'accès complet.

### 1.4 Plateforme

Application web, usage principal sur desktop. Un effort de design responsive doit être fourni pour rendre l'application utilisable sur mobile et tablette, même si l'expérience desktop reste la priorité (les vues heatmap annuelles ne peuvent pas être parfaitement optimisées pour 380px de large, et le client en est conscient).

Langue de l'interface : français uniquement. Pas de besoin d'internationalisation.

---

## 2. Entités et données

### 2.1 Véhicule

Chaque véhicule possède un cycle de vie complet (acquisition → exploitation → sortie) avec historique d'événements. Un véhicule vendu en 2024 n'apparaît plus sur le planning 2025 mais reste consultable dans l'historique.

**Champs obligatoires (nécessaires au calcul fiscal) :**

*Identification administrative :*

- Numéro d'immatriculation
- Marque et modèle
- Catégorie de réception européenne : M1, N1 (au sens des homologations européennes — VP / VU sont les codes utilisateur correspondants, voir glossaire)
- Type de véhicule (champ utilisateur, code court) : VP (voiture particulière, catégorie M1), VU (véhicule utilitaire, catégorie N1 — camionnettes ayant au moins 2 rangs de places et affectées au transport de personnes, pick-ups ≥ 5 places assises)
- Carrosserie (rubrique J.2 du certificat d'immatriculation) : ex. CI (conduite intérieure), BB (camionnette), CTTE (camionnette), BE (pick-up), HB (handicap)…
- Nombre de places assises (rubrique S.1 du certificat d'immatriculation)
- Date de première immatriculation en France
- Date de première immatriculation à l'origine (étrangère pour un véhicule importé d'occasion ; identique à la date de 1ère immat. France pour un véhicule neuf immatriculé en France) — utilisée pour la règle de bascule WLTP/NEDC/PA et pour l'évaluation de l'ancienneté du véhicule
- Date de première affectation à l'activité économique de l'entreprise utilisatrice (utilisée pour la condition CIBS L. 421-119-1, 2° relative aux véhicules affectés avant le 1er janvier 2006)

*Caractéristiques techniques fiscalement déterminantes :*

- Méthode de mesure des émissions applicable : WLTP, NEDC, ou puissance administrative (déterminée selon les règles détaillées en section 5)
- Émissions de CO₂ (g/km) — champ WLTP et/ou NEDC selon le cas
- Puissance administrative (chevaux fiscaux / chevaux administratifs)
- Type de carburant / source d'énergie : Essence, Diesel, Électrique, Hydrogène, Hybride rechargeable, Hybride non rechargeable, GPL, GNV, Superéthanol E85, combinaison électricité+hydrogène
- Type de moteur thermique sous-jacent (uniquement pour les véhicules hybrides) : Essence, Diesel, ou « sans objet » si le véhicule est non hybride. Indispensable pour la catégorisation polluants : un véhicule hybride dont le moteur thermique est de type Diesel ne relève pas de la catégorie 1 (qui suppose un « moteur thermique à allumage commandé », c'est-à-dire essence) mais de la catégorie « véhicules les plus polluants ».
- Norme Euro (Euro 1 à Euro 6d-ISC-FCM) — nécessaire pour déterminer la catégorie de polluants atmosphériques
- Catégorie d'émissions de polluants atmosphériques : E, 1, ou "véhicules les plus polluants" (correspond aux catégories Crit'Air — voir section 5.2)
- Masse en ordre de marche (kg) — case G de la carte grise (utile pour les calculs futurs et la TAI)

*Champs conditionnels (qualification fiscale fine) :*

- Véhicule accessible en fauteuil roulant ou aménagé pour conduite par personne handicapée (booléen) — déclenche l'exonération CIBS art. L. 421-123 / L. 421-136
- Affectation au transport de personnes (booléen) — uniquement pour les camionnettes N1 ≥ 2 rangs de places, sert à déterminer si le véhicule entre dans le champ d'application des taxes annuelles
- Banquette amovible avec deuxième rang de places (booléen) — pour les camionnettes N1 dont la qualification dépend de la présence effective d'un second rang
- Usage spécial (booléen) — pour les véhicules M1 affectés à des usages dérogatoires (transport public de personnes, taxi, VTC, ambulance, école de conduite, compétition sportive, etc.)
- Usage en remontées mécaniques skiables (booléen) — pour les pick-ups N1 ≥ 5 places affectés à cet usage spécifique (exonération applicable)

**Champs complémentaires :**

- Photo du véhicule (optionnel)
- Numéro de série / VIN
- Couleur
- Kilométrage courant (optionnel, saisie libre)
- Statut courant : actif, en maintenance, vendu, détruit, autre
- Notes libres

**Cycle de vie — événements historisés :**

- Date d'acquisition (entrée dans la flotte)
- Événements de maintenance (avec date, type, description libre)
- Contrôle technique (avec date, résultat)
- Sinistres (avec date, description)
- Indisponibilités (voir 2.5)
- Date et motif de sortie de flotte (vente, destruction, transfert, autre)

Chaque événement de cycle de vie est enregistré avec une date et une description. Les coûts associés ne sont PAS gérés par l'application.

**Auto-complétion depuis l'immatriculation :** lors de l'enregistrement d'un nouveau véhicule, un mécanisme doit tenter de récupérer automatiquement les données techniques depuis la plaque d'immatriculation (via une API carte grise ou SIV si disponible). Ce pré-remplissage est un bonus, pas un prérequis : la saisie manuelle doit toujours être possible comme fallback, et l'utilisateur doit pouvoir corriger ou compléter les données auto-remplies.

**Historisation des caractéristiques fiscales :** les caractéristiques d'un véhicule qui sont déterminantes pour le calcul fiscal (source d'énergie, type de moteur thermique sous-jacent, taux d'émission CO₂ WLTP/NEDC, puissance administrative, norme Euro, catégorie polluants, masse en ordre de marche, méthode de mesure des émissions applicable, carrosserie, nombre de places assises, accessibilité fauteuil roulant, affectation au transport de personnes pour les N1, banquette amovible avec deuxième rang, usage spécial, usage en remontées mécaniques skiables, date de première affectation à l'activité économique de l'entreprise) sont **historisées** : chaque modification effective de l'une de ces caractéristiques (ex. conversion E85 d'un véhicule essence, transformation N1 → ajout d'un second rang de places) crée une nouvelle version datée, sans écraser la précédente. Le moteur de calcul Floty lit la version effective à la date de chaque jour d'attribution. À distinguer de la **correction d'une saisie erronée** (édition pure de la version existante, sans création d'une nouvelle entrée historique). Les caractéristiques administratives non fiscales (numéro d'immatriculation, marque, modèle, VIN, couleur, kilométrage, statut courant) ne sont pas historisées.

### 2.2 Entreprise utilisatrice

Chaque entreprise possède un cycle de vie (ajout, modification, désactivation) avec historique.

**Champs :**

- Raison sociale
- SIREN / SIRET
- Adresse du siège
- Contact principal (nom, téléphone, email)
- Code court (2-3 lettres, ex: "AC" pour ACME) — utilisé pour l'affichage compact dans la saisie rapide
- Couleur attribuée — utilisée dans la vue véhicule (timeline par entreprise)
- Statut : active / inactive
- Date de création dans l'application
- Date de désactivation (le cas échéant)

**Note importante :** la société de location (propriétaire de la flotte) est implicite dans l'application. Elle n'apparaît pas comme entité gérée. Cependant, l'architecture ne doit pas empêcher de l'ajouter comme entité à l'avenir si le besoin évolue.

### 2.3 Conducteur

Un conducteur est toujours rattaché à une seule entreprise. Un conducteur ne peut pas être partagé entre entreprises.

**Champs :**

- Prénom, Nom
- Entreprise de rattachement (relation)
- Statut : actif / inactif
- Date d'ajout
- Date de désactivation (le cas échéant)

**Fonctionnalité "Remplacer par…" :** quand un conducteur quitte une entreprise, il ne doit pas être supprimé (l'historique fiscal le référence). Il est désactivé. Un bouton "Remplacer par…" dans sa fiche doit proposer de transférer toutes ses attributions futures (à partir d'une date donnée) vers un autre conducteur de la même entreprise.

### 2.4 Attribution

L'attribution est l'entité pivot centrale de l'application. Elle lie un véhicule, une entreprise, un conducteur (optionnel) et une date.

**Règles :**

- Granularité : le jour (pas de demi-journée)
- Calendrier : 7 jours par semaine (lun-dim)
- **Contrainte d'unicité : un véhicule ne peut être attribué qu'à une seule entreprise par jour.** L'application doit bloquer toute tentative de doublon.
- Le conducteur est optionnel au moment de l'attribution — il peut être renseigné plus tard.
- Les attributions passées sont modifiables et supprimables, avec un **historique des modifications** (audit trail). Chaque changement enregistre la date de modification, l'ancien état et le nouvel état.
- L'année de référence est l'année civile (1er janvier — 31 décembre). Les années bissextiles (366 jours) doivent être correctement gérées dans les calculs de prorata.

### 2.5 Indisponibilité

En plus des attributions aux entreprises, un véhicule peut être indisponible pour des raisons non liées à une entreprise.

**Types d'indisponibilité :**

- Maintenance / entretien
- Contrôle technique
- Sinistre / réparation
- Fourrière / immobilisation administrative
- Autre (champ libre)

Une indisponibilité a une date de début, une date de fin (optionnelle si en cours), un type et une description libre. Les jours d'indisponibilité apparaissent sur les vues planning comme des jours bloqués (ni libres, ni attribués à une entreprise).

**Impact fiscal des indisponibilités :** seul le type **« Fourrière / immobilisation administrative »** réduit le numérateur du prorata fiscal (jours non comptés comme affectation à l'entreprise utilisatrice). Les autres types (Maintenance, Contrôle technique, Sinistre, Autre) n'ont pas d'effet sur le calcul fiscal — l'entreprise reste considérée comme affectataire pendant la durée de la réparation ou de l'opération. Cette règle est issue de la lecture stricte de la doctrine fiscale (BOFiP BOI-AIS-MOB-10-30-10 § 190) et appliquée selon le principe de prudence. Floty doit afficher de manière visible, lors de la saisie d'une indisponibilité, si le type sélectionné a ou n'a pas un impact fiscal.

### 2.6 Barème fiscal

Chaque année doit avoir sa propre configuration de barèmes fiscaux. Les barèmes doivent être éditables par l'utilisateur dans l'interface (section Paramètres), et idéalement pré-remplis avec les valeurs officielles connues.

Le détail des barèmes et des règles de calcul est traité en section 5.

### 2.7 Utilisateur

- Email
- Mot de passe (hashé)
- Prénom, Nom
- Rôle : non géré en V1 (tous les utilisateurs ont les mêmes droits)

---

## 3. Vues et interface

### 3.1 Architecture de navigation

**Barre latérale gauche (sidebar) permanente :**

- **VUE D'ENSEMBLE**
  - Dashboard
- **PLANNING**
  - Vue globale
  - Vue par entreprise
  - Vue par véhicule
  - Saisie hebdomadaire
- **DONNÉES**
  - Flotte (véhicules)
  - Entreprises & conducteurs
- **FISCALITÉ**
  - Déclarations
  - Analytics multi-années
- **PARAMÈTRES**
  - Barèmes fiscaux
  - Exonérations
  - Configuration générale

**Barre supérieure (top bar) permanente :**

- Barre de recherche globale (⌘K) : recherche unifiée véhicules (par immat, modèle), entreprises, conducteurs
- Sélecteur d'année (flèches gauche/droite) : visible sur toutes les vues sauf Flotte, Entreprises et Paramètres. Le changement d'année ne recharge pas la page — seul le contenu de la grille se met à jour.
- Bouton "Exporter" (PDF)
- Bouton "Nouvelle attribution" : toujours visible, ouvre le wizard d'attribution rapide

### 3.2 Dashboard

Page d'accueil, vue d'ensemble rapide de l'état de la flotte.

**Indicateurs clés (KPIs) :**

- Taux d'occupation global (semaine en cours + tendance)
- Nombre de véhicules actifs / total
- Nombre d'entreprises actives
- Estimation des taxes totales pour l'année en cours

**Alertes et points d'attention :**

Zone listant les éléments nécessitant une action, calculés automatiquement :

- Véhicules sous-utilisés (sous un seuil paramétrable d'occupation)
- Déclarations fiscales en attente de génération (année N-1)
- Contrôles techniques à prévoir (si une date CT est renseignée)
- Conducteurs récemment ajoutés sans attribution
- Informations d'exonération (ex: "Tesla Model 3 — exonération CO₂ confirmée")

**Accès rapides :**

- Ouvrir la vue globale
- Ouvrir la saisie hebdomadaire
- Attribution rapide
- Générer les déclarations

**Graphique d'occupation :**

Courbe d'occupation mensuelle de la flotte sur les 12 derniers mois.

### 3.3 Vue planning globale — Heatmap annuelle

C'est la vue maîtresse de l'application. Elle donne en un regard l'état d'utilisation de l'ensemble de la flotte sur une année complète.

**Structure :**

- **En lignes** : chaque véhicule de la flotte (actif sur l'année sélectionnée). Chaque ligne affiche une mini-fiche : badge VP/VU, immatriculation (monospace), modèle. Le véhicule est cliquable → fiche véhicule.
- **En colonnes** : 52 semaines de l'année, regroupées visuellement par mois (séparateurs ou en-têtes de mois).
- **Chaque cellule** : un carré représentant une semaine pour un véhicule donné.

**Contenu des cellules :**

- **Couleur de fond** = densité d'utilisation de la semaine (nombre de jours utilisés toutes entreprises confondues, de 0 à 7). Dégradé : blanc (0 jour) → bleu clair (1-2) → bleu moyen (3-4) → bleu foncé (5-6) → bleu très foncé (7/7).
- **Chiffre** (optionnel) = nombre de jours utilisés, affiché en surimpression si > 0.
- **Aucune information sur quelle entreprise utilise le véhicule.** C'est une vue de densité pure. Le détail vient au zoom.

**Interactions :**

- **Hover** : tooltip indiquant "Semaine X · Y jour(s) / 7".
- **Clic sur une cellule** : ouvre le panneau latéral de détail de la semaine (voir 3.7).
- **Clic sur un véhicule (colonne gauche)** : navigue vers la fiche véhicule.

**Indicateurs complémentaires (au-dessus de la grille) :**

- Nombre de semaines complètes (7/7) sur l'ensemble de la flotte
- Nombre de semaines vides
- Moyenne d'utilisation (jours / semaine / véhicule)
- Véhicule le plus utilisé

**Filtres et tri :**

- Filtrer par type (VP/VU), par carburant, par statut
- Trier par immatriculation, modèle, taux d'occupation

**Légende de densité :** affichée en haut de la grille, montrant le dégradé de 0 à 7 jours.

### 3.4 Vue planning par entreprise

Même structure heatmap que la vue globale, mais avec une couche d'information supplémentaire spécifique à l'entreprise sélectionnée.

**Sélecteur d'entreprise :** rangée de pills/boutons en haut de la vue, une par entreprise. L'entreprise sélectionnée est visuellement mise en avant.

**Carte récapitulative de l'entreprise sélectionnée :**

Affichée entre le sélecteur et la grille. Contient :

- Logo/couleur, raison sociale, SIREN
- Nombre total de jours-véhicule utilisés sur l'année
- Nombre de véhicules utilisés / total flotte
- Estimation des taxes pour l'année sélectionnée

**Contenu des cellules :**

- **Couleur de fond** = densité d'utilisation globale du véhicule sur cette semaine (toutes entreprises confondues). C'est la même information que dans la vue globale. Elle répond à "y a-t-il de la place ?".
- **Chiffre en surimpression** = nombre de jours utilisés **par cette entreprise en particulier** sur cette semaine. Il répond à "comment est répartie l'utilisation de cette entreprise ?".

Ces deux informations sont **indépendantes et complémentaires**. On ne cherche pas à croiser les deux sémantiquement — la couleur parle de disponibilité, le chiffre parle d'usage. Savoir si un véhicule est partagé avec d'autres entreprises sur une même semaine n'est pas une information pertinente dans cette vue (le zoom semaine la fournit si besoin).

**Guide de lecture :** une barre explicative au-dessus de la grille qui montre clairement : "Fond = densité globale du véhicule · Chiffre = jours utilisés par [Entreprise X]".

**Indicateur fiscal LCD par couple véhicule × entreprise :** sur la ligne de chaque véhicule, en marge de la grille, un compteur affiche le **cumul annuel** des jours d'utilisation du couple (véhicule, entreprise sélectionnée) ainsi que **l'impact fiscal estimé** au moment T (en euros). Tant que le cumul reste ≤ 30 jours, le compteur affiche « X jours · 0 € (exonération LCD applicable) ». Dès que le cumul dépasse 30 jours, le compteur affiche le montant des taxes dues au prorata. Cet affichage rend visible en temps réel l'effet de l'exonération LCD documentée dans `taxes-rules/2024.md` R-2024-021 et permet à l'utilisateur de prendre des décisions d'attribution éclairées (par exemple : choisir un autre véhicule moins utilisé par cette entreprise pour préserver l'exonération).

### 3.5 Vue par véhicule

Fiche détaillée d'un véhicule donné.

**Sélecteur de véhicule :** dropdown permettant de naviguer entre véhicules sans revenir à la liste.

**Caractéristiques techniques :** affichées en cartes compactes en haut (type, carburant, CO₂, norme Euro, puissance fiscale, date 1ère immat.).

**Historique du cycle de vie :**

Timeline chronologique des événements : acquisition, maintenances, CT, sinistres, vente/destruction. Chaque événement est affiché avec sa date, son type et sa description.

**Planning annuel du véhicule :**

Une seule ligne de 52 semaines, mais cette fois les **cellules sont segmentées par couleur d'entreprise** (puisqu'on est au niveau d'un seul véhicule, l'information "quelle entreprise" est pertinente ici). Les indisponibilités sont affichées dans une couleur distincte (gris rayé ou autre indicateur visuel).

Légende des entreprises avec leur couleur et le nombre de jours utilisés par chacune.

**Tableau de répartition fiscale :**

Pour chaque entreprise ayant utilisé ce véhicule sur l'année :

- Nom de l'entreprise
- Nombre de jours d'utilisation
- Prorata (jours / nombre de jours de l'année)
- Taxe CO₂ calculée (prorata × tarif annuel CO₂)
- Taxe polluants calculée (prorata × tarif annuel polluants)
- Total

Ligne de total en bas du tableau.

### 3.6 Saisie hebdomadaire (mode tableur)

Vue optimisée pour la saisie en masse, pensée pour le cas d'usage "le lundi matin, je rentre les attributions de la semaine passée".

**Structure :**

- **Sélecteur de semaine** (flèches gauche/droite + numéro de semaine + dates correspondantes).
- **En lignes** : les véhicules (avec mini-fiche : badge VP/VU, immat, modèle).
- **En colonnes** : les 7 jours de la semaine (Lun → Dim) avec les dates correspondantes.
- **Chaque cellule** : soit vide (jour libre — affiche un "+" cliquable avec bordure en pointillés), soit remplie (entreprise + conducteur si renseigné).

**Interactions de saisie :**

- Cliquer sur une cellule vide → dropdown pour choisir l'entreprise, puis optionnellement le conducteur
- Taper un code court entreprise au clavier (ex: "AC") pour attribution rapide
- Navigation clavier : flèches pour se déplacer, Tab/Enter pour valider
- Ctrl+D ou raccourci similaire pour dupliquer la cellule du dessus
- Sélection de plusieurs cellules consécutives pour attribution en masse (même entreprise + même conducteur)
- **Attribution des jours restants :** un clic permet d'attribuer automatiquement tous les jours libres d'une semaine pour un véhicule donné à une entreprise (et si la semaine est entièrement vide, cela revient à sélectionner la semaine complète)

**Import CSV :** bouton permettant d'importer des attributions depuis un fichier CSV/Excel structuré. Nécessaire pour la reprise de l'historique.

### 3.7 Panneau latéral de détail de semaine

S'ouvre en drawer (panneau glissant depuis la droite) quand on clique sur une cellule dans les vues planning (globale ou par entreprise).

**Contenu :**

- En-tête : semaine N, année, véhicule (immat + modèle)
- Grille de 7 jours (Lun → Dim) : chaque jour est un slot visuel montrant soit l'entreprise utilisatrice (avec sa couleur, son code court, et le conducteur si renseigné), soit un espace vide cliquable pour attribuer, soit une indisponibilité.
- Liste des entreprises utilisatrices sur cette semaine (avec le nombre de jours pour chacune)
- **Bouton "Attribuer des jours libres"** : ouvre un mini-formulaire pour attribuer en un clic tous les jours encore libres à une entreprise
- **Bouton de duplication** (V2 potentielle — non prioritaire en V1)

### 3.8 Wizard d'attribution rapide

Modal en 3 étapes, accessible depuis le bouton "Nouvelle attribution" dans la top bar.

**Étape 1 — Qui ?**

- Sélection de l'entreprise (grille de cartes cliquables avec couleur + nom)
- Sélection du conducteur parmi les conducteurs actifs de cette entreprise (optionnel — peut être laissé vide)

**Étape 2 — Quand ?**

- Sélection de dates : date de début + date de fin
- Pas de récurrence en V1

**Étape 3 — Quel véhicule ?**

- Liste des véhicules disponibles sur la période demandée (seuls les véhicules sans attribution ni indisponibilité sur les dates demandées sont affichés)
- Triés par pertinence (véhicules déjà récemment utilisés par cette entreprise en premier)
- Indicateur "Recommandé" sur le premier résultat

**Validation :** un récapitulatif clair avant confirmation (entreprise, conducteur, dates, véhicule). Confirmation en un clic.

### 3.9 Flotte — Liste des véhicules

Vue tabulaire de tous les véhicules gérés (actifs et historiques).

**Colonnes :**

- Immatriculation
- Modèle
- Type (VP/VU)
- Carburant
- CO₂ (g/km)
- Date 1ère immatriculation
- Taux d'occupation sur l'année en cours (barre de progression + pourcentage)
- Statut

**Fonctionnalités :**

- Recherche par plaque, modèle
- Filtres par type, carburant, statut
- Clic sur une ligne → fiche véhicule détaillée (vue 3.5)
- Bouton "Ajouter un véhicule" → formulaire de création avec auto-complétion depuis l'immatriculation

### 3.10 Entreprises & conducteurs

Vue sous forme de cartes, une par entreprise. Chaque carte est dépliable.

**Carte (fermée) :**

- Couleur + code court, raison sociale, SIREN
- Indicateurs : nombre de conducteurs, nombre de véhicules utilisés, taxes estimées pour l'année

**Carte (dépliée) :**

- Liste des conducteurs avec avatar initiales, nom, et bouton "Remplacer par…"
- Bouton "Ajouter un conducteur"
- Lien vers la vue planning par entreprise

**Actions :**

- Ajouter une entreprise
- Modifier une entreprise
- Désactiver une entreprise (avec conservation de l'historique)
- Ajouter / modifier / désactiver un conducteur

### 3.11 Fiscalité — Déclarations

Dashboard des déclarations fiscales pour une année donnée (l'année sélectionnée dans la top bar, qui représente l'année d'utilisation — la déclaration effective se fait en janvier de l'année suivante).

**KPIs :**

- Total des taxes pour l'année
- Nombre de déclarations à générer / vérifiées / générées / envoyées

**Grille de cartes par entreprise :**

Chaque carte affiche :

- Entreprise (couleur, code, nom, SIREN)
- Statut de la déclaration : Brouillon / Vérifiée / Générée / Envoyée
- Montant total des taxes
- Nombre de véhicules concernés
- Nombre de jours cumulés

**Clic sur une carte → modal de détail déclaration :** tableau complet véhicule par véhicule avec tous les calculs intermédiaires (voir section 5 pour les calculs). Bouton "Générer le PDF officiel".

**Bouton "Générer toutes les déclarations" :** génère en masse les PDF pour toutes les entreprises.

### 3.12 Fiscalité — Analytics multi-années

Vue analytique permettant de comparer l'évolution des taxes d'une année sur l'autre.

**Graphique principal :** barres empilées par année (taxe CO₂ + taxe polluants).

**KPIs :**

- Évolution sur la période (% et montant absolu)
- Taxe moyenne par véhicule
- Véhicule le plus taxant

**Détection de variations :** alertes automatiques identifiant les changements significatifs (ex: "Iveco Daily — +40% en 2026, lié au nouveau barème CO₂").

**Filtres :** par entreprise, par véhicule, par type de taxe.

### 3.13 Paramètres

**Barèmes fiscaux :** configuration année par année, éditables. Pour chaque année, on définit :

- Le barème WLTP (tranches d'émissions de CO₂ + tarif marginal par tranche)
- Le barème NEDC (tranches d'émissions de CO₂ + tarif marginal par tranche)
- Le barème Puissance administrative (tranches de CV + tarif marginal par tranche)
- Le barème Polluants atmosphériques (tarif par catégorie : E, 1, véhicules les plus polluants)

Les barèmes doivent être pré-remplis avec les valeurs officielles connues (voir section 5).

**Exonérations :** liste des règles d'exonération actives, avec possibilité de les activer/désactiver.

**Avertissement :** mention permanente rappelant que les barèmes doivent être validés annuellement par un expert-comptable avant génération des déclarations officielles.

---

## 4. Fonctionnalités transverses

### 4.1 Recherche globale (⌘K)

Accessible depuis n'importe quelle vue. Recherche unifiée dans les véhicules (par immat, modèle), les entreprises (par nom, SIREN), les conducteurs (par nom).

Résultats affichés en temps réel avec navigation clavier. Sélection d'un résultat → navigation vers la fiche correspondante.

### 4.2 Sélecteur d'année

Persistant en haut de l'interface. Permet de naviguer entre les années avec flèches gauche/droite. Ne sont proposées que les années contenant des données.

Le changement d'année doit être fluide — pas de rechargement complet de page, juste une mise à jour du contenu.

### 4.3 Export PDF

Périmètre retenu **en V1** : un seul document PDF généré par l'application, le **récapitulatif fiscal par entreprise × année** (cf. § 3.11 et § 5.7). Immuable après génération, avec snapshot de toutes les données utilisées pour le calcul (cf. ADR-0003).

Ajout **en V1.2** : le PDF « **facture commerciale aux entreprises utilisatrices** » produit par le module facturation — document distinct du récapitulatif fiscal, exposant le montant des loyers que la société de location facture à chaque entreprise utilisatrice pour la mise à disposition des véhicules (cf. § 9.1).

Les autres exports envisagés au départ du projet (fiche véhicule individuelle, vue planning en PDF) sont **exclus de la roadmap** — valeur client jugée insuffisante pour justifier le développement.

### 4.4 Import CSV/Excel — exclu du périmètre Floty

L'import CSV/Excel est **exclu de manière durable** du périmètre Floty. Justification : les données historiques disponibles côté client sont inexploitables (trop d'erreurs de saisie manuelle antérieure, cœur même de la motivation à commander Floty). La reprise d'historique se fait par saisie contrôlée via les interfaces dédiées (saisie hebdomadaire tableur, wizard d'attribution rapide). Voir ADR-0007 pour le détail du raisonnement et § 9.4 pour le positionnement général dans la roadmap.

### 4.5 Historique et audit trail

Chaque modification d'attribution est historisée (date, utilisateur, ancien état, nouvel état). Cet historique est consultable dans la fiche véhicule et dans le détail de la déclaration fiscale.

### 4.6 Alertes (V1 = dashboard uniquement)

Les alertes sont calculées automatiquement et affichées sur le dashboard. Elles ne donnent pas lieu à des notifications email/push en V1, mais l'architecture doit permettre d'ajouter des notifications futures.

Types d'alertes :

- Véhicules sous-utilisés (seuil paramétrable)
- Déclarations à générer pour l'année N-1
- CT à prévoir (si date renseignée)
- Conducteurs sans attribution récente
- Exonérations applicables détectées

### 4.7 Dates souples pour l'historique

Les champs de dates ne doivent pas être trop restrictifs — il faut pouvoir renseigner manuellement des dates passées (antérieures à la date du jour) pour la saisie de l'historique rétroactif. Les validations de cohérence (ex: date de fin > date de début) s'appliquent, mais pas de blocage sur les dates passées.

---

## 5. Règles fiscales — Calcul des taxes

> **⚠️ Cette section est le cœur de l'application.** Les calculs doivent être exacts et conformes à la législation française en vigueur pour chaque année. Les barèmes sont configurables année par année dans les paramètres, et doivent être validés par un expert-comptable avant toute génération de document officiel.

### 5.1 Cadre légal

Depuis le 1er janvier 2022, la TVS (taxe sur les véhicules de sociétés) est remplacée par deux taxes annuelles sur l'affectation des véhicules de tourisme à des fins économiques :

1. **Taxe annuelle sur les émissions de dioxyde de carbone (taxe CO₂)**
2. **Taxe annuelle sur les émissions de polluants atmosphériques (taxe polluants)**

Ces taxes sont déclarées annuellement au titre de l'année civile précédente (les taxes de l'année 2025 sont déclarées en janvier 2026).

Les entreprises redevables sont celles qui utilisent des véhicules de tourisme pour leur activité économique. Dans le cas de Renaud, la société de location qui détient les véhicules est **exonérée** en tant que loueur (article L.421-128 du CIBS). Ce sont les entreprises utilisatrices qui sont redevables, au prorata de leur durée d'utilisation.

### 5.2 Taxe annuelle sur les émissions de CO₂

Le tarif annuel est calculé de manière **progressive par tranches** (tarif marginal), comme l'impôt sur le revenu. Le barème applicable dépend de la méthode de mesure des émissions du véhicule :

**Détermination de la méthode applicable :**

- **Barème WLTP** : véhicules dont la première immatriculation en France a eu lieu à compter du 1er mars 2020, pour lesquels les émissions de CO₂ ont été mesurées selon la méthode WLTP.
- **Barème NEDC** : véhicules utilisés par l'entreprise depuis le 1er janvier 2006 et dont la première mise en circulation a eu lieu après le 1er juin 2004, pour lesquels les émissions ont été mesurées selon la méthode NEDC.
- **Barème Puissance administrative** : tous les autres véhicules (véhicules n'ayant pas fait l'objet d'une réception européenne, ou véhicules anciens ne relevant ni du WLTP ni du NEDC).

**Barème WLTP — Tranches et tarifs marginaux par année :**

| Tranche (g CO₂/km) | 2024 | 2025 | 2026 | 2027 |
|---|---|---|---|---|
| 0 à X (seuil zéro) | 0–14 : 0 €/g | 0–9 : 0 €/g | 0–4 : 0 €/g | 0 : 0 €/g |
| Tranche à 1 €/g | 15–55 | 10–50 | 5–45 | 1–40 |
| Tranche à 2 €/g | 56–63 | 51–58 | 46–53 | 41–48 |
| Tranche à 3 €/g | 64–95 | 59–90 | 54–85 | 49–80 |
| Tranche à 4 €/g | 96–115 | 91–110 | 86–105 | 81–100 |
| Tranche à 10 €/g | 116–135 | 111–130 | 106–125 | 101–120 |
| Tranche à 50 €/g | 136–155 | 131–150 | 126–145 | 121–140 |
| Tranche à 60 €/g | 156–175 | 151–170 | 146–165 | 141–160 |
| Tranche à 65 €/g | ≥ 176 | ≥ 171 | ≥ 166 | ≥ 161 |

**Exemple de calcul WLTP :** véhicule émettant 100 g CO₂/km, année 2026 :
Tarif = 4×0 + (45−4)×1 + (53−45)×2 + (85−53)×3 + (100−85)×4 = 0 + 41 + 16 + 96 + 60 = **213 €** (tarif annuel plein)

**Barème Puissance administrative — Tarifs par année :**

| Tranche (CV) | 2024 | 2025 | 2026 |
|---|---|---|---|
| Jusqu'à 3 CV | 1 500 €/CV | 1 750 €/CV | 2 000 €/CV |
| 4 à 6 CV | 2 250 €/CV | 2 500 €/CV | 3 000 €/CV |
| 7 à 10 CV | 3 750 €/CV | 4 250 €/CV | 4 500 €/CV |
| 11 à 15 CV | 4 750 €/CV | 5 000 €/CV | 5 250 €/CV |
| ≥ 16 CV | 6 000 €/CV | 6 250 €/CV | 6 500 €/CV |

*Note : le barème puissance administrative fonctionne aussi par fractions et tarif marginal, exactement comme le barème WLTP mais sur les CV au lieu des g/km.*

### 5.3 Taxe annuelle sur les émissions de polluants atmosphériques

Le tarif est forfaitaire par véhicule et par an, déterminé par la catégorie d'émissions de polluants (correspondant aux vignettes Crit'Air) :

| Catégorie | Description | 2024 | 2025 | 2026 (à partir du 1er mars) | 2027 |
|---|---|---|---|---|---|
| **E** | Électrique, hydrogène, ou combinaison des deux | 0 € | 0 € | 0 € | 0 € |
| **1** | Essence/hybride/gaz compatibles Euro 5 ou Euro 6 | 100 € | 100 € | 130 € | 160 € |
| **Véhicules les plus polluants** | Tous les autres véhicules (correspond à Crit'Air 2 à 5 + non classés) | 500 € | 500 € | 650 € | 800 € |

### 5.4 Calcul du prorata d'affectation

Pour chaque combinaison véhicule × entreprise × année, le prorata est :

**Prorata = Nombre de jours d'utilisation / Nombre de jours dans l'année (365 ou 366)**

Le nombre de jours d'utilisation est compté directement depuis les attributions enregistrées dans l'application.

### 5.5 Formule de calcul du montant dû

Pour chaque combinaison véhicule × entreprise × année :

```
Montant taxe CO₂ = Tarif annuel CO₂ du véhicule × Prorata
Montant taxe polluants = Tarif annuel polluants du véhicule × Prorata
Total = Montant taxe CO₂ + Montant taxe polluants
```

Arrondi à l'euro le plus proche (0,50 arrondi à 1).

### 5.6 Exonérations

Les véhicules suivants sont **exonérés** des deux taxes :

- Véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène, ou une combinaison des deux
- Véhicules accessibles en fauteuil roulant ou avec aménagements spécifiques pour conduite par une personne handicapée

**Abattements pour véhicules E85 (superéthanol) :** à compter du 1er janvier 2025, abattement de 40% sur les émissions de CO₂ OU de 2 CV sur la puissance administrative, sauf si les émissions dépassent 250 g/km ou si la puissance dépasse 12 CV.

**Exonération hybrides (jusqu'au 31/12/2024 uniquement) :** sont exonérés de taxe CO₂ les véhicules dont la source d'énergie répond aux deux conditions cumulatives suivantes :

- **Combinaison de sources d'énergie** parmi l'un des deux jeux suivants (les hybrides Diesel-électrique ne sont **pas** couverts) :
  - (a) électricité ou hydrogène **+** gaz naturel, GPL, essence ou superéthanol E85 ;
  - (b) gaz naturel ou GPL **+** essence ou superéthanol E85.
- **Seuils d'émissions ou de puissance** :
  - **Régime standard** : émissions de CO₂ ≤ 60 g/km (WLTP) ou ≤ 50 g/km (NEDC), ou puissance administrative ≤ 3 CV.
  - **Aménagement transitoire pour véhicules récents** : pour un véhicule dont l'ancienneté depuis sa première immatriculation n'excède pas trois années, les seuils ci-dessus sont **doublés** (≤ 120 g/km WLTP, ≤ 100 g/km NEDC, ou ≤ 6 CV PA).

**Cette exonération n'existe plus à partir de 2025.**

L'application doit appliquer automatiquement les exonérations en fonction des caractéristiques du véhicule et de l'année de calcul. L'utilisateur doit pouvoir forcer manuellement une exonération ou la désactiver en cas de situation particulière.

### 5.7 Récapitulatif fiscal (document PDF)

Le document généré pour chaque entreprise × année doit contenir :

**En-tête :**

- Année d'imposition
- Raison sociale de l'entreprise, SIREN, adresse
- Date de génération du document

**Tableau détaillé par véhicule :**

Pour chaque véhicule utilisé par l'entreprise sur l'année :

- Immatriculation, modèle
- Caractéristiques fiscales : type de carburant, émissions CO₂, norme Euro, puissance fiscale, méthode de calcul (WLTP/NEDC/PA)
- Nombre de jours d'utilisation
- Prorata (%)
- Tarif annuel CO₂ (avant prorata)
- Taxe CO₂ (après prorata)
- Tarif annuel polluants (avant prorata)
- Taxe polluants (après prorata)
- Total par véhicule

**Totaux :**

- Total taxe CO₂
- Total taxe polluants
- **Total à déclarer**

**Pied de page / mention légale :**

"Ce document est un récapitulatif détaillé servant de pièce justificative. Le report sur le formulaire officiel de déclaration (annexe 3310-A-SD ou déclaration 3517 selon le régime d'imposition) doit être effectué par le service comptable de l'entreprise."

### 5.8 Taxe annuelle incitative au verdissement (TAI)

Cette taxe, introduite par la loi de finances 2025, concerne **uniquement les entreprises disposant d'un parc d'au moins 100 véhicules**. Elle vise à faire respecter les quotas de renouvellement des flottes en véhicules à faibles émissions.

Quotas : 15% en 2025, 18% en 2026, 25% en 2027.

**Note :** la flotte de Renaud est d'environ 100 véhicules. Si chaque entreprise est considérée individuellement, aucune n'atteint probablement le seuil de 100. En revanche, si le calcul se fait au niveau du groupe, le seuil pourrait être atteint. **Ce point doit être validé avec l'expert-comptable de Renaud.** L'application doit prévoir la possibilité d'ajouter cette taxe dans les paramètres si nécessaire.

---

## 6. Intelligence artificielle — Cas d'usage pertinents

> L'IA n'est intégrée que là où elle apporte une valeur réelle et mesurable, pas comme gadget.

### 6.1 Détection d'anomalies et alertes intelligentes

L'IA analyse les données d'utilisation pour détecter des patterns anormaux que l'utilisateur n'aurait pas vus :

- Véhicule sous-utilisé depuis X semaines (seuil adaptatif basé sur l'historique, pas un seuil fixe)
- Changement brutal dans le profil d'utilisation d'une entreprise
- Incohérences de saisie (ex: même conducteur attribué sur deux véhicules le même jour dans des entreprises différentes — en théorie impossible si bien saisi, mais détectable si l'import crée des doublons)

### 6.2 Suggestions d'attribution

Quand l'utilisateur ouvre le wizard d'attribution rapide, l'IA peut :

- Recommander le véhicule le plus pertinent (basé sur : utilisation récente par la même entreprise, taux d'occupation, caractéristiques similaires aux véhicules habituellement utilisés par cette entreprise)
- Suggérer des créneaux de sous-occupation à combler

### 6.3 Analyse fiscale prédictive

- Projection du montant des taxes sur l'année en cours basée sur le rythme d'utilisation actuel
- Identification des véhicules dont le remplacement par un véhicule moins polluant générerait la plus forte économie fiscale
- Estimation de l'impact fiscal d'un changement de flotte (ex: "remplacer les 3 diesels les plus polluants par des hybrides économiserait X €/an")

### 6.4 Recherche en langage naturel

La barre de recherche (⌘K) pourrait comprendre des requêtes en langage naturel : "quels véhicules sont libres la semaine prochaine ?" ou "combien de taxes ACME a-t-elle payé en 2025 ?".

### 6.5 Mise en œuvre

Ces fonctionnalités IA ne sont pas toutes nécessaires en V1. L'ordre de priorité suggéré :

1. **V1** : Alertes intelligentes (dashboard) + recommandation de véhicule dans le wizard d'attribution
2. **V2** : Analyse fiscale prédictive + détection d'anomalies avancée
3. **V3** : Recherche en langage naturel

---

## 7. Performances et contraintes techniques

### 7.1 Volumes de données

- ~100 véhicules
- ~30 entreprises
- ~100+ conducteurs
- ~365 × 100 = 36 500 cellules d'attribution par an
- Historique sur 3+ années (extensible)

### 7.2 Performance de la grille heatmap

La vue globale affiche 100 véhicules × 52 semaines = 5 200 cellules. Le rendu doit être fluide (<200ms pour l'affichage complet). Le changement d'année doit être instantané (préchargement de l'année précédente et suivante en arrière-plan).

### 7.3 Intégrité des données fiscales

Les calculs fiscaux doivent être déterministes et reproductibles. Un même jeu de données + mêmes barèmes = même résultat à l'euro près. Les arrondis se font uniquement en fin de calcul (pas d'arrondis intermédiaires).

---

## 8. UX — Principes directeurs

### 8.1 Économie de clics

Chaque action fréquente doit nécessiter le minimum de clics. L'attribution d'un véhicule pour une semaine entière doit pouvoir se faire en 3-4 clics maximum (un clic pour sélectionner les jours libres, un pour choisir l'entreprise, un pour valider).

### 8.2 Confirmation légère

Les actions courantes (attribution, modification) ne déclenchent pas de modal de confirmation. Une notification discrète en bas de l'écran avec possibilité d'annuler (undo) pendant 10 secondes.

Les actions destructives (suppression d'un véhicule, suppression d'une entreprise) déclenchent une confirmation explicite.

### 8.3 Actions contextuelles

Clic droit (ou menu "…") sur une cellule du planning : "Voir détail", "Attribuer", "Libérer", "Voir fiche véhicule". Gain de clics pour les utilisateurs avancés.

### 8.4 Impression/export pensés dès le départ

Chaque vue doit être exportable en PDF propre. Le design doit anticiper le rendu imprimé (marges, contraste, lisibilité sans couleur si nécessaire).

### 8.5 Navigation temporelle fluide

Le sélecteur d'année en top bar permet de naviguer entre les années sans perdre le contexte de vue. Si je suis sur la vue par entreprise pour ACME, changer d'année doit garder ACME sélectionnée et juste rafraîchir les données.

---

## 9. Évolutions futures identifiées (hors V1)

Cette section recense les fonctionnalités et évolutions non incluses dans le périmètre V1 (MVP). Le détail du cadrage V1 et le positionnement de chaque fonctionnalité sur une version cible sont documentés dans **ADR-0007** (`project-management/decisions/0007-perimetre-v1-mvp.md`) et la vue d'ensemble navigable est tenue dans **`project-management/roadmap.md`**.

### 9.1 V1.2 — Module facturation loyers (ajout programmé entre V1 et V2)

- **Grilles tarifaires de location** (journalier, mensuel, etc.) configurables par la société de location.
- **Calcul automatique des loyers** dus par chaque entreprise utilisatrice à partir des attributions déjà enregistrées dans Floty.
- **PDF de facture commerciale** aux entreprises utilisatrices (distinct du PDF récapitulatif fiscal — montant commercial, pas fiscal).
- **Envoi des factures** aux entreprises utilisatrices.

Cette extension s'appuie sur les mêmes données d'attribution que le volet fiscal, en les exploitant dans un flux parallèle dédié à la facturation commerciale. Elle ne modifie pas le modèle fiscal.

### 9.2 V2 — Évolutions fonctionnelles

Ces fonctionnalités sont candidates à une V2, avec priorisation précise après livraison V1 et retours d'usage :

- **Analytics multi-années** : comparaison inter-années, détection de variations, graphiques, filtres par entreprise / véhicule / type de taxe.
- **Alertes sur le dashboard** (véhicules sous-utilisés, CT à prévoir, conducteurs inactifs, exonérations applicables…) — logique de seuils et d'analyse, sans IA.
- **Audit trail complet** des modifications d'attribution (auteur, date, ancien état, nouvel état).
- **Récurrence des attributions** (copier un pattern de semaine sur N semaines).
- **Gestion de rôles et permissions** (plusieurs comptes avec droits différenciés).
- **Formulaire officiel pré-rempli** type 3310-A-SD ou 3517 en plus du récapitulatif fiscal.
- **Notifications par email** (déclarations à générer, alertes).
- **Mode multi-société** (la société de location comme entité gérée avec son propre reporting).

### 9.3 V3 — Sophistications et IA

Ces fonctionnalités supposent un socle parfaitement stabilisé :

- **Détection d'anomalies par IA** sur les patterns d'utilisation.
- **Suggestions d'attribution IA** dans le wizard (optimisation LCD, utilisation récente, taux d'occupation).
- **Analyse fiscale prédictive** (projections, recommandations de renouvellement de flotte, simulation d'impacts).
- **Recherche en langage naturel** (barre ⌘K avec NLP).
- **Auto-complétion depuis API SIV** pour pré-remplir les caractéristiques véhicule depuis l'immatriculation.
- **Application mobile dédiée**.
- **Intégration comptable** (export vers logiciels comptables).
- **Notifications push**.

### 9.4 Exclusions durables (non planifiées)

- **Import CSV / Excel** : exclu de manière durable, indépendamment de la version. Raison : les données historiques disponibles côté client sont inexploitables (trop d'erreurs de saisie manuelle antérieure), et la reprise d'historique se fait par saisie contrôlée dans Floty. Si à l'avenir Floty était déployé chez d'autres clients disposant de données propres, la décision pourrait être revue (voir ADR-0007).
- **Gestion de la TAI (Taxe annuelle incitative au verdissement)** : hors périmètre Floty de manière définitive. La TAI est applicable aux propriétaires de flotte ≥ 100 véhicules ; or Floty calcule uniquement les taxes dues par les entreprises utilisatrices (qui, prises individuellement, n'atteignent jamais ce seuil). Cf. cartographie phase 0.
- **PDF fiche véhicule individuelle** et **PDF planning** : non prévus. Seuls le PDF récapitulatif fiscal (V1) et le PDF facture commerciale (V1.2) sont dans la roadmap.
- **Gestion des coûts véhicule** (maintenance, carburant, assurance) : non prévue — la facturation loyers (V1.2) ne couvre que la refacturation entre la société de location et les entreprises utilisatrices, pas la gestion des coûts opérationnels des véhicules.

---

## 10. Glossaire

| Terme | Définition |
|---|---|
| VP | Voiture Particulière — code utilisateur Floty correspondant à la catégorie de réception européenne **M1** (véhicule conçu pour le transport de personnes, comportant au plus 8 places assises outre celle du conducteur) |
| VU | Véhicule Utilitaire — code utilisateur Floty correspondant à la catégorie de réception européenne **N1** (véhicule conçu pour le transport de marchandises ayant un poids maximal ≤ 3,5 tonnes). Inclut camionnettes (carrosserie BB, CTTE) et pick-ups (BE) |
| M1 / N1 | Catégories de réception européenne au sens des homologations véhicules. Utilisées dans la section 5 (règles fiscales) en cohérence avec la formulation du CIBS art. L. 421-2. M1 = équivalent technique de VP, N1 = équivalent technique de VU |
| WLTP | Worldwide Harmonized Light Vehicles Test Procedure — norme de mesure des émissions depuis mars 2020 |
| NEDC | New European Driving Cycle — ancienne norme de mesure des émissions |
| CV | Chevaux administratifs (puissance fiscale) |
| Taxe CO₂ | Taxe annuelle sur les émissions de dioxyde de carbone |
| Taxe polluants | Taxe annuelle sur les émissions de polluants atmosphériques |
| TAI | Taxe Annuelle Incitative au verdissement des flottes |
| TVS | Taxe sur les Véhicules de Sociétés — ancienne appellation, remplacée en 2022 |
| Crit'Air | Système de classification des véhicules selon leur niveau d'émissions polluantes |
| Prorata | Rapport entre le nombre de jours d'utilisation et le nombre de jours de l'année |
| Tarif marginal | Tarif applicable à chaque fraction (tranche) d'émissions ou de puissance |
| CIBS | Code des Impositions sur les Biens et Services |
| BOFiP | Bulletin Officiel des Finances Publiques |

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 20/04/2026 | Renaud / Micha MEGRET | Version initiale du cahier des charges. |
| 1.1 | 23/04/2026 | Micha MEGRET | § 5.6 — Précision de l'exonération hybride 2024 (CIBS art. L. 421-125) suite à vérification croisée des sources primaires : explicitation des deux jeux de combinaisons de sources d'énergie autorisées (excluant les hybrides Diesel-électrique) et ajout de l'aménagement transitoire pour véhicules dont l'ancienneté n'excède pas 3 ans (seuils doublés). |
| 1.2 | 23/04/2026 | Micha MEGRET | § 2.1 — Quatre ajustements suite à l'instruction du sous-dossier `2024/cas-particuliers/` : (a) harmonisation du critère N1 taxable « camionnettes ≥ 3 rangs » → « camionnettes ≥ 2 rangs ET affectées au transport de personnes » (texte CIBS art. L. 421-2) ; (b) ajout du champ « type de moteur thermique sous-jacent » (Essence / Diesel / sans objet) pour les véhicules hybrides — indispensable pour la catégorisation polluants (un hybride Diesel ne relève pas de la catégorie 1) ; (c) ajout d'une mention explicite sur l'historisation des caractéristiques fiscales du véhicule (versions datées sans écrasement, distinction entre correction de saisie et changement effectif) ; § 2.5 — Précision du seul type d'indisponibilité réduisant le numérateur du prorata fiscal : « Fourrière / immobilisation administrative » (BOFiP BOI-AIS-MOB-10-30-10 § 190 + principe de prudence) ; les autres types n'ont pas d'effet fiscal. |
| 1.3 | 23/04/2026 | Micha MEGRET | § 2.1 — Étoffement de la liste des champs véhicule pour intégrer les caractéristiques nécessaires aux algorithmes documentés dans `2024/cas-particuliers/decisions.md` : ajout des sections « Identification administrative » (catégorie de réception européenne, carrosserie, nombre de places assises, date de 1ère immat. à l'origine, date de 1ère affectation à l'activité économique), « Caractéristiques techniques fiscalement déterminantes » (organisation des champs existants), « Champs conditionnels » (accessibilité fauteuil roulant, affectation transport personnes pour N1, banquette amovible 2 rangs, usage spécial, usage remontées mécaniques skiables). Mise à jour de la section « Historisation des caractéristiques fiscales » pour intégrer les nouveaux champs. § 10 (Glossaire) — Précision du mapping VP↔M1, VU↔N1 + ajout d'une entrée M1/N1 pour clarifier la cohabitation des codes utilisateur (VP/VU) et des termes techniques CIBS (M1/N1). |
| 1.4 | 23/04/2026 | Micha MEGRET | § 3.4 (Vue planning par entreprise) — Ajout d'un paragraphe « Indicateur fiscal LCD par couple véhicule × entreprise » spécifiant que la vue par entreprise doit afficher en marge de chaque ligne véhicule un compteur du cumul annuel des jours d'utilisation du couple (véhicule, entreprise) et l'impact fiscal estimé en temps réel. Cette exigence intègre la mécanique d'exonération LCD à cumul par couple documentée dans `taxes-rules/2024.md` R-2024-021 et résout l'incertitude Z-2024-002 (clarification du modèle économique de Renaud confirmée le 23/04/2026). |
| 1.5 | 23/04/2026 | Micha MEGRET | Refonte du § 9 (Évolutions futures) pour répercuter le cadrage V1 acté par ADR-0007 : positionnement clair de chaque fonctionnalité non-V1 sur V1.2 / V2 / V3 / exclusions durables. Refonte du § 4.3 (Export PDF) pour préciser que seul le récapitulatif fiscal est en V1 et que le PDF de facture commerciale arrive en V1.2. § 4.4 (Import CSV/Excel) révisé : fonctionnalité exclue durablement du périmètre Floty (données historiques inexploitables côté client). |

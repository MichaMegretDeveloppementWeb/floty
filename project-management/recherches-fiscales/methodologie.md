# Méthodologie de recherche fiscale — Floty

> **Statut** : Version 0.2 — révisée après première passe d'annotations
> **Auteur** : Micha MEGRET (prestataire)
> **Date de rédaction initiale** : 22 avril 2026
> **Dernière révision** : 22 avril 2026
> **Périmètre** : Cadre méthodologique pour la production des règles de calcul fiscales intégrées à Floty (V1 : années 2024, 2025, 2026)

---

## 1. Objet et portée du document

Ce document définit la méthodologie rigoureuse et reproductible que nous appliquons à notre travail de recherche fiscale française pour Floty. Il fixe :

- les sources autorisées et leur hiérarchie
- la méthode de croisement et de validation des informations
- le format standardisé des livrables produits
- les critères de complétude d'une recherche
- le traitement des incertitudes et des zones grises
- le workflow opérationnel de production

Il sert simultanément de **guide opérationnel** pour notre équipe et de **document contractuel implicite** pour le client : il atteste que notre démarche est cadrée, professionnelle et auditable.

---

## 2. Contexte et enjeu

Floty repose sur un principe d'architecture fort : **la fiscalité est de la donnée, pas du code**. Les règles de calcul des taxes (taxe CO₂, taxe polluants atmosphériques) et des modulations associées (exonérations, abattements) sont stockées en base de données et interprétées par un moteur de calcul.

En V1, ces règles ne sont **pas éditables depuis l'application**. Seul nous, prestataire, pouvons les ajouter, les modifier ou les corriger via des seeders/migrations déployés sur le serveur. Le client (Renaud) en a la pleine consultation via une page dédiée, et soumet l'ensemble à validation par son expert-comptable après livraison.

Cette architecture place une **responsabilité majeure sur la qualité de notre travail de recherche**. Une règle erronée dans la base produira un calcul erroné, qui produira une déclaration fiscale erronée, qui exposera l'entreprise utilisatrice à un redressement fiscal. Notre travail doit donc être :

- **exact** au regard de la législation française en vigueur
- **complet** : pas de cas non couvert
- **traçable** : chaque chiffre, chaque règle est rattaché à une source
- **justifié** : chaque interprétation de notre part est argumentée et documentée

---

## 3. Périmètre du travail de recherche

### 3.1 Périmètre temporel

Trois années fiscales pour la V1 :

- **2024**
- **2025**
- **2026**

Les années ultérieures (2027 et au-delà) feront l'objet d'une prestation distincte, à mener à mesure que les barèmes officiels sont publiés.

### 3.2 Principe d'exhaustivité

Notre engagement est l'**exhaustivité** : Floty doit couvrir l'intégralité des taxes, contributions, redevances et autres prélèvements obligatoires que l'administration fiscale française impose à une entreprise utilisatrice de véhicules au titre de leur usage à des fins économiques. Nous ne partons donc **pas** d'une liste pré-établie qui risquerait d'omettre des dispositifs.

Pour garantir cette exhaustivité, le travail de recherche s'ouvre par une **phase 0 — Cartographie des taxes applicables** (cf. workflow §9), dont le livrable identifie nominativement, pour la France, l'ensemble des prélèvements concernés. Cette cartographie devient la base à partir de laquelle on instruit ensuite, taxe par taxe, les règles de calcul, les exonérations et les abattements.

### 3.3 Critère de redevabilité

Le périmètre fiscal de Floty se définit par **un critère unique et invariable** : nous couvrons toute taxe dont **l'entreprise utilisatrice du véhicule** est légalement redevable.

À l'inverse, nous excluons :

- toute taxe dont le redevable est le **propriétaire de la flotte** (la société de location de Renaud), même si elle est techniquement liée au véhicule
- toute taxe à la charge d'un **tiers** (constructeur, importateur, conducteur en nom propre)

Ce critère sera le filtre appliqué pendant la cartographie de phase 0.

### 3.4 Hors périmètre V1 (exclusions identifiées dès maintenant)

- **Taxe annuelle incitative au verdissement (TAI)** : exclue parce que les entreprises utilisatrices, prises individuellement, ne disposent pas d'une flotte ≥ 100 véhicules. Le seuil est éventuellement atteint au niveau du propriétaire (la société de location de Renaud), mais celle-ci n'est pas dans notre périmètre de calcul. Documenté pour vigilance future si l'architecture du groupe évolue.
- **Formulaire officiel pré-rempli (3310-A-SD, 3517 ou autres)** : hors périmètre V1. Notre livrable est un récapitulatif détaillé servant de pièce justificative ; le report sur formulaire officiel reste à la charge du service comptable de chaque entreprise utilisatrice.

Toute autre exclusion sera décidée à l'issue de la phase 0 (cartographie), sur la base du critère de redevabilité §3.3, et documentée explicitement.

---

## 4. Hiérarchie des sources

Toute information utilisée doit pouvoir être rattachée à une source identifiée. Trois cercles de fiabilité, du plus autoritaire au moins autoritaire.

### 4.1 Sources primaires (autorité légale)

Ce sont les seules sources qui font foi devant l'administration fiscale.

- **Légifrance** (legifrance.gouv.fr) — texte officiel des lois et codes :
  - Code des Impositions sur les Biens et Services (CIBS), articles L.421-93 et suivants
  - Code Général des Impôts (CGI), pour les références antérieures à la refonte CIBS
  - Lois de finances annuelles (texte intégral)
- **BOFiP-Impôts** (bofip.impots.gouv.fr) — doctrine fiscale officielle commentant les textes
- **impots.gouv.fr** — site de l'administration fiscale, notices et formulaires officiels

**Règle absolue** : toute valeur numérique (tarif, seuil, borne de tranche) doit être tracée à une source primaire. Aucun chiffre n'entre dans nos décisions sans cette traçabilité.

### 4.2 Sources secondaires (vulgarisation officielle)

Émanations de l'administration française, à but pédagogique. Excellentes pour valider une compréhension générale, mais ne font pas autorité en cas de divergence avec les sources primaires.

- **service-public.fr** — fiches pratiques pour entreprises et particuliers
- **entreprendre.service-public.fr** — section dédiée aux entreprises
- **economie.gouv.fr** — communications de Bercy

### 4.3 Sources tertiaires (croisement professionnel)

Éditeurs juridiques, presse spécialisée, organismes professionnels. À utiliser pour **vérifier notre interprétation** des sources primaires, jamais comme source unique.

- **Editions Francis Lefebvre** (efl.fr) — référence en droit fiscal des entreprises
- **Les Échos Solutions** — articles spécialisés
- **L'Argus de l'Assurance**, **L'Argus** (auto) — pour les aspects techniques véhicules
- **Captain Contrat**, **Compta-Online**, **Legalstart** — vulgarisation pratique
- **CNPA, FNTR** — organismes professionnels du secteur auto/transport

### 4.4 Sources interdites

- Forums de discussion généralistes
- Articles de blog non signés ou sans citation de source primaire
- Contenu généré par IA tierce non vérifiable
- Documents marketing d'éditeurs de logiciels concurrents

---

## 5. Méthode de croisement et de validation

### 5.1 Règle des trois sources

Pour qu'un fait, une règle ou une valeur numérique entre dans nos décisions, il doit avoir été :

1. **Identifié** sur au moins une source primaire (obligatoire)
2. **Confirmé** par au moins une source secondaire ou primaire indépendante
3. **Croisé** avec au moins une source tertiaire pour valider notre interprétation pratique

Si une seule source est trouvée et qu'elle est primaire, nous documentons ce point comme à confiance moyenne et le signalons pour validation par l'expert-comptable.

### 5.2 Traitement des divergences

Quand des sources se contredisent :

- **Si une source primaire est en jeu** : la source primaire l'emporte systématiquement. Les divergences avec les sources secondaires/tertiaires sont documentées avec mention "interprétation tertiaire écartée car contraire à la lettre du texte".
- **Si seules des sources secondaires/tertiaires se contredisent** : nous tranchons en faveur de la source la plus récente, la plus précise (citant un article spécifique), et la plus prudente fiscalement (calcul majorant en cas de doute).
- **Documentation obligatoire** de toute divergence rencontrée, dans le fichier `recherches.md` correspondant.

### 5.3 Validation numérique

Pour tout calcul présenté dans nos décisions :

- Au moins **un exemple chiffré complet** est produit, étape par étape, et vérifié manuellement.
- Quand des exemples officiels existent (BOFiP en publie parfois), nous reproduisons l'exemple à l'identique pour valider notre interprétation. Si nous obtenons un résultat différent, nous comprenons pourquoi avant de continuer.

---

## 6. Format des livrables

### 6.1 Arborescence

```
project-management/
├── recherches-fiscales/
│   ├── methodologie.md                    [ce document]
│   ├── cartographie-taxes.md              [livrable de la phase 0 — voir §9.1]
│   ├── incertitudes.md                    [index transverse synthétique des zones grises]
│   ├── 2024/
│   │   ├── taxe-co2/
│   │   │   ├── recherches.md
│   │   │   ├── decisions.md
│   │   │   ├── sources.md
│   │   │   └── incertitudes.md
│   │   ├── taxe-polluants/
│   │   │   ├── recherches.md
│   │   │   ├── decisions.md
│   │   │   ├── sources.md
│   │   │   └── incertitudes.md
│   │   ├── exonerations/
│   │   │   ├── recherches.md
│   │   │   ├── decisions.md
│   │   │   ├── sources.md
│   │   │   └── incertitudes.md
│   │   ├── abattements/
│   │   │   ├── recherches.md
│   │   │   ├── decisions.md
│   │   │   ├── sources.md
│   │   │   └── incertitudes.md
│   │   └── cas-particuliers/
│   │       ├── recherches.md
│   │       ├── decisions.md
│   │       ├── sources.md
│   │       └── incertitudes.md
│   ├── 2025/   (même structure ; sous-dossiers déterminés après cartographie)
│   └── 2026/   (même structure ; sous-dossiers déterminés après cartographie)
└── taxes-rules/
    ├── 2024.md                            [synthèse exécutive 2024]
    ├── 2025.md
    └── 2026.md
```

**Notes sur l'arborescence :**

- Les **sous-dossiers par taxe** présents dans chaque année (`taxe-co2`, `taxe-polluants`, `exonerations`, `abattements`, `cas-particuliers`) sont les sous-dossiers de base. Ils sont susceptibles d'évoluer au gré des découvertes de la phase 0 — certaines années pourront contenir des sous-dossiers supplémentaires si une taxe nouvelle apparaît, ou voir un sous-dossier supprimé si une taxe disparaît.
- Le fichier `sources.md` est positionné au niveau de chaque taxe (et non au niveau de l'année), pour faciliter la citation contextuelle des sources et leur mise à jour sans toucher aux autres dossiers.
- Le fichier `incertitudes.md` est présent **à deux niveaux** :
  - Un fichier `incertitudes.md` **par sous-dossier de taxe** : détail complet des incertitudes identifiées au cours de l'instruction de cette taxe pour cette année. C'est la **source de vérité**.
  - Un fichier `incertitudes.md` **à la racine de `recherches-fiscales/`** : **index transverse synthétique** qui agrège toutes les incertitudes (toutes années, toutes taxes) sous forme de tableaux courts (référence, sujet, priorité, statut) avec lien vers le sous-dossier d'origine. Ce fichier sert de checklist consolidée pour le client et son expert-comptable. Il ne duplique pas le détail.

### 6.2 Format `recherches.md`

Document narratif accumulant les matériaux bruts collectés. Structure :

```markdown
# Recherches — [Taxe ou sujet] — [Année]

## Sources consultées
- [Liste numérotée, format 5.4 ci-dessous]

## Synthèse de la législation applicable
- Texte de référence : [article CIBS, article CGI, loi de finances]
- Champ d'application : [à qui s'applique cette taxe / règle]
- Date d'entrée en vigueur : [date précise]
- Date de fin d'application : [date ou "en vigueur"]

## Extraits pertinents
[Citations textuelles des sources primaires, indentées en blockquote, avec référence à la source]

## Valeurs numériques relevées
[Tableaux structurés des barèmes, tarifs, bornes, seuils]

## Cas particuliers identifiés dans les sources
[Liste des cas évoqués par les sources, avec leur traitement officiel]

## Divergences ou ambiguïtés rencontrées
[Documentation explicite de toute zone grise, contradiction entre sources, lacune doctrinale]

## Questions ouvertes
[Points non résolus par la recherche, à transférer dans `decisions.md` ou à signaler à l'expert-comptable]
```

### 6.3 Format `decisions.md`

Document de prise de décision argumentée. Pour chaque point qui a nécessité un choix de notre part :

```markdown
# Décisions — [Taxe ou sujet] — [Année]

## Décision 1 — [Titre court]

**Contexte** : [pourquoi une décision est nécessaire]

**Options envisagées** :
- Option A : [description, conséquences]
- Option B : [description, conséquences]
- Option C : [description, conséquences]

**Décision retenue** : [option choisie]

**Justification** : [arguments fondés sur les sources, le bon sens fiscal, la prudence]

**Niveau de confiance** : Haute / Moyenne / Basse

**À valider par expert-comptable** : Oui / Non

**Conséquences sur l'implémentation** : [comment cette décision se traduit dans les règles seedées]

---

## Décision 2 — …
```

### 6.4 Format `sources.md` (par taxe et par année)

Bibliographie de la taxe étudiée pour l'année concernée. Une entrée par source consultée. Il y a donc **un `sources.md` par sous-dossier de taxe** (et non un seul `sources.md` au niveau de l'année), pour permettre une citation contextuelle et une mise à jour ciblée.

```markdown
# Sources consultées — [Taxe ou sujet] — [Année]

## Sources primaires

### S1 — [Titre court de la source]
- **Type** : Loi / Article CIBS / BOFiP / Notice fiscale
- **Référence officielle** : [ex: Article L.421-119 CIBS]
- **URL** : [lien direct]
- **Date de consultation** : [JJ/MM/AAAA]
- **Date de dernière mise à jour de la source** : [si disponible]
- **Utilisée pour** : [liste des décisions ou éléments du `recherches.md` où cette source est citée]

## Sources secondaires
[Même format]

## Sources tertiaires
[Même format]
```

### 6.5 Format `incertitudes.md` (à deux niveaux)

Les incertitudes sont documentées à deux niveaux complémentaires : un fichier détaillé par sous-dossier de taxe (source de vérité), et un fichier global synthétique à la racine de `recherches-fiscales/` (index pour le client et son expert-comptable).

#### 6.5.1 — `incertitudes.md` par sous-dossier (source de vérité détaillée)

Un fichier `incertitudes.md` est créé dans chaque sous-dossier `recherches-fiscales/{année}/{sujet}/`. Il documente exhaustivement chaque incertitude identifiée lors de l'instruction du sujet concerné. C'est la **source de vérité** : toute consultation détaillée d'une incertitude se fait à partir de ce fichier.

```markdown
# Zones grises et points à valider — [Taxe ou sujet] — Exercice [AAAA]

> Ce document détaille les incertitudes, zones grises et décisions à confiance basse ou moyenne
> identifiées au cours de l'instruction de [taxe ou sujet] pour [AAAA].
> Il alimente le fichier transverse `recherches-fiscales/incertitudes.md` qui en présente la synthèse.

## Z-AAAA-NNN — [Titre court]
- **Localisation** : [renvoi vers le `decisions.md` ou `recherches.md` du présent sous-dossier]
- **Nature de l'incertitude** : [zone grise documentaire / divergence entre sources / décision à confiance basse / autre]
- **Notre choix actuel** : [résumé de la décision ou de l'hypothèse retenue par défaut]
- **Conséquence si erroné** : [impact fiscal estimé]
- **Action attendue** : Validation expert-comptable / Recherche complémentaire / Décision client / Autre
- **Statut** : Ouvert / Résolu (avec date et issue)

---

## Z-AAAA-NNN — …
```

**Règle de rattachement** : une incertitude est rattachée au sous-dossier où elle a été identifiée pour la première fois. Si elle est ultérieurement enrichie par l'instruction d'un autre sujet, le détail consolidé reste dans le fichier d'origine et un **renvoi** est ajouté dans le fichier `incertitudes.md` du sous-dossier qui apporte l'enrichissement.

#### 6.5.2 — `incertitudes.md` à la racine (index transverse synthétique)

Un fichier global `recherches-fiscales/incertitudes.md` agrège toutes les incertitudes sous forme de tableaux courts (sans dupliquer le détail). Il sert de **checklist consolidée** pour le client et son expert-comptable, permettant en un seul document d'avoir la vision transverse des points à valider.

```markdown
# Index transverse des zones grises et points à valider — Recherche fiscale Floty

> Ce document est l'**index synthétique** de toutes les incertitudes…
> agrège — sans dupliquer le détail — les fichiers `incertitudes.md` situés dans chaque sous-dossier.

## Année [AAAA]

### Issues de l'instruction de [taxe ou sujet] — détail dans `[année]/[sujet]/incertitudes.md`

| Référence | Sujet | Priorité | Statut |
|---|---|---|---|
| Z-AAAA-NNN | [titre court] | Haute / Moyenne / Basse | Ouvert / Résolu |

### Synthèse [AAAA]

[Phrase courte de bilan, ex: « N incertitudes ouvertes : X haute, Y moyennes, Z basses »]
```

**Mise à jour** : à chaque ajout / modification / clôture d'une incertitude dans un fichier de sous-dossier, l'index global est mis à jour en conséquence (la ligne correspondante dans le tableau de synthèse).

### 6.6 Format `taxes-rules/{année}.md`

Synthèse exécutive consolidée pour l'année, prête à servir de cahier des charges à l'écriture des seeders. Document **sans ambiguïté**, sans renvoi à des recherches : il se suffit à lui-même.

```markdown
# Règles fiscales — Année [AAAA]

## Vue d'ensemble
[Résumé en 5-10 lignes des taxes applicables et de leurs particularités pour cette année,
incluant les éventuelles taxes nouvelles, supprimées ou modifiées par rapport à l'année précédente]

## Règle R-AAAA-001 — [Nom court de la règle]

- **Type** : Tarification / Exonération / Abattement / Modification de calcul / autre
- **Taxe concernée** : [nom officiel de la taxe]
- **Période d'application** : du JJ/MM/AAAA au JJ/MM/AAAA
- **Champ d'application** (véhicules concernés) : [conditions précises sur les caractéristiques véhicule]
- **Caractéristiques véhicule consommées par la règle** : [liste exhaustive des champs véhicule lus en entrée par cette règle, ex: type de carburant, taux d'émission CO₂ WLTP, masse en ordre de marche, puissance administrative, date de 1ère immatriculation, norme Euro, catégorie polluants…]
- **Caractéristiques véhicule produites ou modifiées par la règle** (si applicable) : [pour les abattements qui modifient une valeur d'entrée d'une autre règle, ex: applique un coefficient de 0,60 sur taux CO₂ WLTP]
- **Base légale** : [article CIBS + lien BOFiP + autres références]
- **Description en français** : [phrase compréhensible par un non-spécialiste]
- **Logique de calcul / d'application** : [pseudo-code ou formule mathématique explicite]
- **Tableau des paramètres** : [tranches, tarifs, seuils, valeurs forfaitaires]
- **Exemple chiffré complet** : [un cas concret avec toutes les caractéristiques véhicule explicites, calculé pas-à-pas]
- **Référence(s) `decisions.md` associées** : [renvois pour traçabilité]
- **Confiance** : Haute / Moyenne / Basse

---

## Règle R-AAAA-002 — …
```

Ces fichiers `taxes-rules/{année}.md` sont **les sources de vérité** qui pilotent les seeders et l'affichage de la page de consultation des règles dans l'application.

---

## 7. Critères de complétude d'une recherche

Une recherche est considérée comme **terminée** uniquement quand toutes les conditions ci-dessous sont remplies. Cette checklist sert de garde-fou.

- [ ] Toutes les règles applicables au sujet sont identifiées et documentées
- [ ] Pour chaque règle : période d'application, champ d'application, paramètres, base légale renseignés
- [ ] Tous les cas particuliers connus sont listés et traités (ou explicitement signalés comme à valider)
- [ ] Toutes les sources sont citées avec référence et date de consultation dans `sources.md`
- [ ] Au moins un exemple chiffré est produit pour chaque règle de tarification
- [ ] Toutes les divergences entre sources sont documentées
- [ ] Toutes les décisions de notre part sont justifiées dans `decisions.md`
- [ ] La synthèse `taxes-rules/{année}.md` correspondante est rédigée et autoporteuse
- [ ] Lecture croisée du dossier complet par soi-même 24h après la dernière modification (recul nécessaire)

---

## 8. Gestion des incertitudes et zones grises

La législation fiscale comporte inévitablement des zones non explicitement couvertes. Notre attitude :

### 8.1 Principe directeur : transparence

Aucune zone grise n'est balayée sous le tapis. Toute incertitude est :

- **documentée** dans le `recherches.md` correspondant (section "Divergences ou ambiguïtés")
- **arbitrée** dans le `decisions.md` correspondant (avec niveau de confiance honnête)
- **agrégée** dans le fichier transverse `recherches-fiscales/incertitudes.md` (cf. format §6.5) à chaque fois qu'elle relève d'une décision à confiance basse ou d'une question ouverte non résolue
- **signalée** à l'expert-comptable pour validation après livraison, via la consultation du fichier `incertitudes.md`

### 8.2 Niveaux de confiance

Nous attribuons un niveau de confiance à chaque décision :

- **Haute** : la décision découle directement et sans ambiguïté de sources primaires concordantes.
- **Moyenne** : la décision est une interprétation raisonnable d'une source primaire, soutenue par des sources tertiaires, mais l'absence d'exemple officiel laisse une marge.
- **Basse** : aucune source ne traite directement le cas, nous appliquons un raisonnement par analogie ou par prudence. Validation expert-comptable indispensable.

### 8.3 Principe de prudence en cas de doute

Quand deux interprétations défendables produisent des résultats fiscalement différents, nous retenons par défaut **la plus majorante** (la plus défavorable au contribuable). Justification : un sous-calcul expose au redressement, un sur-calcul est récupérable. Mais cette règle n'est appliquée qu'en dernier recours, après épuisement des sources.

---

## 9. Workflow opérationnel

### 9.1 Phase 0 — Cartographie des taxes applicables

**Préalable indispensable à toute recherche détaillée.** Avant de plonger dans une taxe en particulier, on identifie l'ensemble des prélèvements concernés.

**Objectif** : produire une liste exhaustive et raisonnée de toutes les taxes, contributions, redevances et autres prélèvements obligatoires applicables en France à un véhicule utilisé à des fins économiques, en filtrant par le critère de redevabilité §3.3 (entreprise utilisatrice uniquement).

**Démarche** :
1. Recensement large via sources primaires et secondaires (CIBS livre IV, BOFiP section "Fiscalité de l'énergie et de l'environnement", service-public.fr section professionnels)
2. Pour chaque prélèvement identifié : nature, redevable, périodicité (annuelle/ponctuelle), critères de déclenchement, base légale
3. Application du filtre de redevabilité : à inclure dans Floty / à exclure (justification)
4. Vérification année par année (un prélèvement peut apparaître/disparaître entre 2024, 2025 et 2026)

**Livrable** : `recherches-fiscales/cartographie-taxes.md`. Ce fichier devient la **table des matières** qui détermine la liste effective des sous-dossiers à traiter dans chaque année.

### 9.2 Ordre de traitement après cartographie

**Par année** : 2024 → 2025 → 2026.
Justification : 2024 est la première année du régime stabilisé après la refonte de 2022 et bénéficie du recul doctrinal le plus complet. 2026 est la plus récente, avec les bascules les plus complexes.

**Par sujet à l'intérieur d'une année** : l'ordre exact dépend du résultat de la cartographie. À titre indicatif, nous traiterons :

1. Les taxes annuelles à enjeu fiscal majeur (typiquement, sur la base du périmètre actuellement pressenti : taxe CO₂ et taxe polluants atmosphériques)
2. Les autres taxes annuelles identifiées par la cartographie
3. Les taxes ponctuelles applicables (si la cartographie en révèle relevant des entreprises utilisatrices)
4. Les exonérations
5. Les abattements
6. Les cas particuliers et règles transitoires

**Note importante** : la liste des sous-dossiers à créer dans `recherches-fiscales/{année}/` peut **varier d'une année à l'autre**. Si une taxe nouvelle apparaît en 2025 mais pas en 2024, son sous-dossier n'existe que dans `2025/`. Si une taxe disparaît en 2026, son sous-dossier n'existe pas dans `2026/`. Ces variations doivent être documentées dans la "Vue d'ensemble" du `taxes-rules/{année}.md` correspondant.

### 9.3 Pour chaque (année × sujet)

1. Collecte initiale des sources primaires
2. Lecture et extraction des éléments pertinents → `recherches.md`
3. Croisement avec sources secondaires et tertiaires
4. Identification des décisions à prendre
5. Argumentation et choix → `decisions.md`
6. Production des exemples chiffrés
7. Mise à jour du `sources.md` du sous-dossier (la taxe traitée)
8. Si décision à confiance basse ou question ouverte → ajout d'une entrée dans `recherches-fiscales/incertitudes.md`
9. Auto-relecture critique 24h plus tard

### 9.4 Au terme d'une année complète

- Production de `taxes-rules/{année}.md` consolidé
- Cohérence vérifiée avec les autres années si chevauchements
- Mise à jour finale de `incertitudes.md` pour cette année

**Pas de notification ni d'échange client à ce stade.** Le client ne fait ses retours qu'après livraison de la V1 de l'application. La phase de recherche se déroule en autonomie complète.

### 9.5 Au terme des trois années

- Revue d'ensemble de cohérence inter-années
- Revue finale de `incertitudes.md` (consolidation et priorisation)
- Préparation du jeu de seeders correspondant
- Livraison à Renaud pour soumission à son expert-comptable

---

## 10. Traçabilité et auditabilité

### 10.1 Citations

Chaque citation textuelle dans `recherches.md` doit indiquer :

- la source précise (numéro S1, S2... référencée dans `sources.md`)
- la position dans la source (article, paragraphe, section)
- la date de consultation

Format recommandé en bas de citation : `— S3, art. L.421-119, consulté le 22/04/2026`

### 10.2 Versionnage

Tous les documents de recherche sont versionnés via git. Chaque modification importante fait l'objet d'un commit nominatif (ex: `Recherche taxe CO2 2024 — barème WLTP — décision sur seuil d'exonération`).

### 10.3 Versioning intra-document

Git capture l'historique technique des modifications. Mais il n'offre pas un visuel direct et lisible des **changements de direction et de décision** au sein d'un document. Pour cette raison, nous doublons git d'un système de versioning **inscrit dans la documentation elle-même**.

**Règle générale** : tout document de recherche ou de décision (`recherches.md`, `decisions.md`, `taxes-rules/{année}.md`, `cartographie-taxes.md`) comporte en fin de fichier une section **"Historique du document"** qui récapitule les versions successives.

```markdown
## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Brouillon initial |
| 0.2 | 25/04/2026 | Micha MEGRET | Refonte §3 suite annotations client ; ajout §6.5 |
| 1.0 | 30/05/2026 | Micha MEGRET | Version livrable |
```

**Règle spécifique aux décisions révisées** : quand une décision est modifiée (et non simplement complétée), le `decisions.md` concerné conserve **la décision originale ET la décision révisée**, avec le motif. On n'écrase jamais une décision passée.

```markdown
## Décision N — [Titre]

[contenu actuel de la décision, à jour]

### Révisions
#### Révision du JJ/MM/AAAA
- **Décision originale** : [résumé court de la décision précédente]
- **Décision révisée** : [résumé court de la nouvelle décision]
- **Motif** : [retour expert-comptable / publication BOFiP / nouvelle interprétation / ...]
- **Impact sur les calculs déjà effectués** : [oui/non, et lesquels]
```

**Règle spécifique aux mises à jour fiscales propagées en production** : quand une décision révisée donne lieu à un re-déploiement de seeder, on l'indique explicitement dans la révision (champ "Impact sur les calculs déjà effectués"). L'application marque alors automatiquement les déclarations affectées comme "Régénération requise" via la mécanique d'invalidation.

L'historique complet est ainsi accessible **à trois niveaux** :
- `git log` — granularité maximale, vision technique
- Sections "Historique du document" — vision synthétique des évolutions majeures par fichier
- Sections "Révisions" dans les `decisions.md` — traçabilité décisionnelle, lisible par le client et son expert-comptable sans recours à git

---

## 11. Limites assumées

Pour l'honnêteté envers nous-mêmes et envers le client :

- **Nous ne sommes ni juristes ni experts-comptables.** Notre travail produit la meilleure interprétation technique possible des textes en vigueur, fondée sur des sources publiques et une démarche rigoureuse. Il doit être validé par un professionnel du chiffre avant tout usage en déclaration officielle.
- **La législation fiscale évolue.** Les règles documentées le sont à un instant T (date de consultation des sources). Toute modification ultérieure (loi de finances, arrêté, décision BOFiP) requiert une mise à jour.
- **Certains cas peuvent ne pas avoir reçu de doctrine officielle au moment de notre recherche.** Nous le signalons explicitement et appliquons le principe de prudence.
- **Notre recherche se limite au droit fiscal français.** Les véhicules immatriculés à l'étranger ou les régimes fiscaux étrangers ne sont pas couverts.

---

## 12. Engagement qualité

Au terme de la recherche pour les trois années 2024, 2025 et 2026, nous garantissons :

- **Une couverture exhaustive de toutes les taxes, contributions et redevances** identifiées par la cartographie de phase 0 comme étant à la charge des entreprises utilisatrices de véhicules à des fins économiques en France, sur les véhicules présents dans le périmètre Floty
- Une traçabilité intégrale entre chaque règle implémentée et sa source légale
- Une documentation auditable, lisible par un tiers (expert-comptable) sans intervention de notre part
- Un récapitulatif consolidé (`incertitudes.md`) des points à confiance basse soumis à validation
- Une réactivité aux corrections demandées par l'expert-comptable du client, dans le cadre d'une prestation complémentaire

Si la cartographie révèle une taxe applicable que nous n'avions pas anticipée, son traitement est intégré au périmètre, sans surcoût et sans renégociation : c'est précisément le sens du principe d'exhaustivité §3.2.

---

## Annexes

### A. Vocabulaire et abréviations

| Terme | Définition |
|---|---|
| CIBS | Code des Impositions sur les Biens et Services (refonte 2022) |
| CGI | Code Général des Impôts (référence historique, partiellement migrée vers CIBS) |
| BOFiP | Bulletin Officiel des Finances Publiques (doctrine fiscale officielle) |
| WLTP | Worldwide Harmonized Light Vehicles Test Procedure (norme d'émissions depuis 2020) |
| NEDC | New European Driving Cycle (ancienne norme d'émissions) |
| PA | Puissance administrative (chevaux fiscaux) |
| TVS | Taxe sur les Véhicules de Sociétés (ancienne appellation, supprimée en 2022) |
| TAI | Taxe annuelle incitative au verdissement (loi de finances 2025 ; hors périmètre Floty car redevable = propriétaire de flotte ≥ 100 véhicules, qui n'est pas une entreprise utilisatrice dans le contexte de Renaud) |

### B. Liste maître des points à vérifier systématiquement pour chaque année

0. **Cartographie comparative** : par rapport à l'année précédente, des taxes nouvelles applicables aux entreprises utilisatrices ont-elles été introduites ? Des taxes existantes ont-elles été supprimées, fusionnées ou remplacées ? La liste des sous-dossiers à créer pour cette année doit refléter ces évolutions.
1. Y a-t-il eu une loi de finances modifiant les barèmes pour cette année ?
2. Le BOFiP a-t-il publié de nouveaux commentaires ?
3. Y a-t-il eu des arrêtés ministériels publiant ou révisant des seuils ?
4. Y a-t-il eu des arrêts du Conseil d'État éclairant l'interprétation d'une règle ?
5. Y a-t-il eu une bascule de tarif en cours d'année ? Si oui, à quelle date précise ?
6. Les exonérations existantes ont-elles été reconduites ou modifiées ?
7. De nouveaux abattements ont-ils été introduits ou supprimés ?
8. La méthode de classement Crit'Air a-t-elle évolué ?
9. Pour mémoire : la TAI reste hors périmètre Floty (entreprises utilisatrices ≠ propriétaire de la flotte). Si la structure du groupe évolue, ce point est à re-évaluer.

### C. Modèles de fiche d'une règle (exemples types, sans valeur engagée)

Trois exemples types pour illustrer la structure attendue dans `taxes-rules/{année}.md`, couvrant les trois grandes catégories : tarification, abattement, exonération. **Les valeurs numériques utilisées sont des illustrations** (notées `[XXX]` ou des nombres ronds), elles seront remplacées par les valeurs effectives issues de la recherche.

#### C.1 — Exemple de règle de tarification (taxe annuelle CO₂, barème WLTP)

```markdown
## Règle R-2026-001 — Taxe CO₂ — Barème WLTP 2026

- **Type** : Tarification progressive par tranches (tarif marginal)
- **Taxe concernée** : Taxe annuelle sur les émissions de dioxyde de carbone
- **Période d'application** : du 01/01/2026 au 31/12/2026
- **Champ d'application** : Véhicules de tourisme (catégories M1 et N1 visées) dont la 1ère immatriculation en France a eu lieu à compter du 01/03/2020 et dont les émissions de CO₂ ont été mesurées selon la procédure WLTP
- **Caractéristiques véhicule consommées par la règle** :
   - Type de véhicule (M1 ou N1 visé)
   - Date de 1ère immatriculation en France
   - Méthode de mesure des émissions de CO₂ (doit être WLTP)
   - Taux d'émission de CO₂ WLTP en g/km (valeur entière issue de la carte grise)
- **Caractéristiques véhicule produites ou modifiées** : aucune
- **Base légale** : Article L.421-XXX CIBS — BOFiP-XXX (à compléter)
- **Description en français** : Tarif annuel calculé de manière progressive par tranches d'émissions de CO₂, exprimées en g/km, selon le barème WLTP en vigueur pour 2026.
- **Logique de calcul** :
   ```
   pour chaque tranche [borne_inf, borne_sup, taux_marginal] du barème :
     fraction_dans_tranche = max(0, min(co2_wltp, borne_sup) - borne_inf)
     contribution = fraction_dans_tranche × taux_marginal
     tarif_annuel_plein += contribution
   montant_a_payer = tarif_annuel_plein × (jours_utilisés / jours_année)
   ```
- **Tableau des paramètres (illustratif)** :

  | Tranche (g CO₂/km) | Tarif marginal |
  |---|---|
  | 0 à [X1] | 0 €/g |
  | [X1+1] à [X2] | 1 €/g |
  | [X2+1] à [X3] | 2 €/g |
  | [...etc...] | [...] |

- **Exemple chiffré complet** :
   - Véhicule : Peugeot 308, type M1, 1ère immatriculation 15/06/2022, méthode WLTP, taux CO₂ WLTP = 100 g/km
   - Année : 2026
   - Entreprise utilisatrice : ACME, jours d'utilisation = 73
   - Calcul du tarif annuel plein : [détail tranche par tranche, somme = T €]
   - Calcul du montant dû : T × (73 / 365) = [résultat] €
- **Référence(s) `decisions.md`** : `2026/taxe-co2/decisions.md` Décision 1, Décision 4
- **Confiance** : [à évaluer après recherche]
```

#### C.2 — Exemple de règle d'abattement (E85)

```markdown
## Règle R-2026-007 — Abattement E85 sur taux CO₂ WLTP

- **Type** : Abattement (modification de caractéristique d'entrée)
- **Taxe concernée** : Taxe annuelle sur les émissions de dioxyde de carbone (impact en cascade sur la règle de tarification)
- **Période d'application** : du 01/01/2026 au 31/12/2026
- **Champ d'application** : Véhicules dont la source d'énergie inclut le superéthanol E85 ET dont le taux d'émission de CO₂ WLTP est ≤ 250 g/km
- **Caractéristiques véhicule consommées par la règle** :
   - Type de carburant (doit inclure E85)
   - Taux d'émission de CO₂ WLTP en g/km (pour vérifier le plafond ≤ 250)
- **Caractéristiques véhicule produites ou modifiées** :
   - **Modifie** : taux d'émission de CO₂ WLTP utilisé en entrée des règles de tarification CO₂ → multiplié par 0,60 (équivalent à un abattement de 40%)
- **Base légale** : Article L.421-XXX CIBS — BOFiP-XXX (à compléter)
- **Description en français** : Les véhicules carburant au superéthanol E85 bénéficient d'un abattement de 40 % sur leur taux d'émission de CO₂ pour le calcul de la taxe CO₂, sauf si leur taux d'émission dépasse 250 g/km auquel cas l'abattement ne s'applique pas.
- **Logique de calcul** :
   ```
   si carburant inclut E85 ET co2_wltp ≤ 250 :
     co2_wltp_effectif = co2_wltp × 0,60
   sinon :
     co2_wltp_effectif = co2_wltp
   # le co2_wltp_effectif est ensuite passé à la règle de tarification CO₂
   ```
- **Tableau des paramètres** :

  | Paramètre | Valeur |
  |---|---|
  | Coefficient appliqué | 0,60 (abattement de 40%) |
  | Plafond d'éligibilité | 250 g CO₂/km |

- **Exemple chiffré complet** :
   - Véhicule : Ford Kuga FlexFuel, type M1, carburant Essence + E85, taux CO₂ WLTP = 130 g/km
   - Année : 2026
   - Vérification d'éligibilité : carburant inclut E85 ✓, 130 ≤ 250 ✓ → abattement applicable
   - Taux CO₂ WLTP effectif après abattement : 130 × 0,60 = 78 g/km
   - La règle de tarification CO₂ sera ensuite appliquée sur 78 g/km au lieu de 130
- **Référence(s) `decisions.md`** : `2026/abattements/decisions.md` Décision 2
- **Confiance** : [à évaluer après recherche]
```

#### C.3 — Exemple de règle d'exonération (véhicule électrique)

```markdown
## Règle R-2026-012 — Exonération totale véhicules électriques

- **Type** : Exonération (annule l'application des règles de tarification dans son champ)
- **Taxe(s) concernée(s)** : Taxe annuelle sur les émissions de CO₂ ET taxe annuelle sur les émissions de polluants atmosphériques
- **Période d'application** : du 01/01/2026 au 31/12/2026
- **Champ d'application** : Véhicules dont la source d'énergie est exclusivement l'électricité, l'hydrogène, ou une combinaison des deux
- **Caractéristiques véhicule consommées par la règle** :
   - Type de carburant (liste exclusive : Électrique, Hydrogène, ou combinaison Électricité+Hydrogène — aucune autre source d'énergie autorisée)
- **Caractéristiques véhicule produites ou modifiées** : aucune
- **Base légale** : Article L.421-XXX CIBS — BOFiP-XXX (à compléter)
- **Description en français** : Les véhicules à motorisation exclusivement électrique, hydrogène ou combinaison des deux sont totalement exonérés des deux taxes annuelles (CO₂ et polluants atmosphériques).
- **Logique de calcul** :
   ```
   si carburant ∈ {Électrique, Hydrogène, Électrique+Hydrogène} :
     montant_taxe_co2 = 0
     montant_taxe_polluants = 0
     # les règles de tarification CO₂ et polluants ne s'appliquent pas pour ce véhicule
   ```
- **Tableau des paramètres** : sans objet (règle binaire d'éligibilité)
- **Exemple chiffré complet** :
   - Véhicule : Tesla Model 3, type M1, carburant Électrique, taux CO₂ WLTP = 0 g/km
   - Année : 2026
   - Entreprise utilisatrice : ACME, jours d'utilisation = 200
   - Vérification d'éligibilité : carburant = Électrique → exonération applicable
   - Montant taxe CO₂ : 0 €
   - Montant taxe polluants : 0 €
   - Total : 0 €
- **Référence(s) `decisions.md`** : `2026/exonerations/decisions.md` Décision 1
- **Confiance** : [à évaluer après recherche]
```

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Brouillon initial soumis pour annotation |
| 0.2 | 22/04/2026 | Micha MEGRET | Refonte §3 (principe d'exhaustivité, critère de redevabilité, hors périmètre resserré sur la TAI) ; refonte §6.1 (sources.md par taxe, ajout incertitudes.md) ; ajout §6.5 (format incertitudes.md) ; enrichissement §6.6 (caractéristiques véhicule consommées/modifiées) ; ajout §9.1 (phase 0 cartographie) et révision §9.2-9.5 (ordre dynamique, suppression notification client) ; renforcement §10.3 (versioning intra-doc à trois niveaux) ; ouverture §12 (engagement non restreint à CO₂+polluants) ; ajout point 0 annexe B ; refonte annexe C (3 modèles : tarification, abattement, exonération avec caractéristiques véhicule explicites) |
| 0.3 | 23/04/2026 | Micha MEGRET | Refonte §6.1 (arborescence) et §6.5 (format incertitudes) pour acter le pattern à deux niveaux : `incertitudes.md` par sous-dossier (source de vérité détaillée) + `incertitudes.md` à la racine de `recherches-fiscales/` (index transverse synthétique). Le fichier global ne contient plus le détail mais des tableaux courts pointant vers les sous-dossiers. |

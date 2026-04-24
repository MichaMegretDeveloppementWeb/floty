# Décisions — Taxe annuelle sur les émissions de polluants atmosphériques — Exercice 2024

> **Statut** : Version 0.1
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 22 avril 2026
> **Renvoi sources** : voir `sources.md` (cotes S1, S2, …) et `recherches.md` pour les analyses détaillées.

---

## Décision 1 — Report intégral des dispositions communes depuis la taxe CO₂

**Contexte** : la taxe annuelle sur les émissions de polluants atmosphériques partage avec la taxe annuelle CO₂ un grand nombre de règles : périmètre véhiculaire (CIBS art. L. 421-2), définition de l'entreprise (L. 421-95), notion d'affectation à des fins économiques (L. 421-95), fait générateur (L. 421-99), territoire de taxation (France métropolitaine + DROM), proportion annuelle d'affectation (L. 421-107), bornes calendaires (année civile), règles d'arrondi (L. 131-1), modalités de déclaration (annexe 3310 A ou formulaire 3517), modalités de paiement, état récapitulatif annuel (L. 421-164), absence de déclaration si montant nul (L. 421-163). Cette « base commune » est doctrinalement traitée par BOI-AIS-MOB-10-30-10 (S3 dans `sources.md` du sous-dossier taxe-co2).

**Options envisagées** :
- **Option A — Re-formaliser intégralement chaque règle dans le présent sous-dossier**, en dupliquant les décisions équivalentes du sous-dossier taxe-co2.
- **Option B — Reporter explicitement par référence** les décisions communes déjà formalisées dans `2024/taxe-co2/decisions.md`, en ne re-documentant ici que ce qui est **spécifique** à la taxe polluants.

**Décision retenue** : **Option B — report par référence**.

Les décisions suivantes du sous-dossier `2024/taxe-co2/` s'appliquent **à l'identique** à la taxe annuelle polluants 2024 :

| Décision dans `2024/taxe-co2/decisions.md` | Sujet | Application à la taxe polluants 2024 |
|---|---|---|
| Décision 2 | Méthode d'arrondi (au total final, half-up commercial) | **Identique** |
| Décision 3 | Année 2024 = année bissextile (366 jours au dénominateur du prorata) | **Identique** |
| Décision 5 | Calcul prorata journalier exclusif (option trimestrielle non implémentée) | **Identique** |
| Décision 7 | Affichage PDF récapitulatif étoffé (justification de la catégorie de polluants à ajouter) | **Adapté** à la taxe polluants : afficher la catégorie polluants retenue et la justification de classement |
| Décision 8 | Définition du « jour d'affectation » (indisponibilités fourrière vs autres) | **Identique** (même base : BOFiP S3 § 190) |
| Décision 9 | Année civile stricte (01/01/2024 → 31/12/2024) | **Identique** |
| Décision 10 | Triangulation des sources primaires (CIBS + BOFiP + notice DGFiP) | **Adaptée** : voir Décision 6 ci-dessous (bibliographie propre à la taxe polluants) |

**Justification** :
- Les sources primaires elles-mêmes (notice DGFiP S1 du présent sous-dossier, qui n'est PAS la même notice que pour la taxe CO₂ — S1 ici = 2858-FC-NOT-SD ; S1 dans le sous-dossier taxe-co2 = 2857-FC-NOT-SD) **renvoient mutuellement** à la même base réglementaire commune et utilisent les mêmes formulations textuelles pour les dispositions communes.
- Le BOFiP `BOI-AIS-MOB-10-30-10` (dispositions communes) est **explicitement transversal** aux deux taxes — il commente les règles applicables à l'ensemble des « taxes sur l'affectation des véhicules à des fins économiques », sans distinguer CO₂ et polluants.
- L'option de duplication créerait du contenu redondant et un risque de divergence à la maintenance (si une décision est révisée d'un côté mais pas de l'autre).
- Le report par référence préserve la traçabilité (tout point peut être retrouvé dans le sous-dossier taxe-co2) sans alourdir le présent document.

**Niveau de confiance** : **Haute**. La nature commune des deux taxes est explicite dans le CIBS (Section 3 unique), dans le BOFiP (BOI-AIS-MOB-10-30-10 unique pour les deux), et dans les notices DGFiP qui se réfèrent mutuellement.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty applique strictement les mêmes règles de prorata, d'arrondi, de gestion bissextile et de gestion des indisponibilités pour les deux taxes.
- Une seule passe de calcul lit les attributions d'un véhicule sur l'année et produit conjointement le montant CO₂ et le montant polluants ; la sommation des deux taxes par redevable précède l'arrondi final unique.
- Le moteur de calcul n'a pas de notion d'« option trimestrielle » — la même UI gère les deux taxes.

---

## Décision 2 — Mécanique de calcul de la taxe polluants : tarif forfaitaire × prorata

**Contexte** : à la différence de la taxe CO₂ (barème progressif par tranches WLTP / NEDC / PA), la taxe polluants est un **tarif forfaitaire unique par catégorie**. La mécanique de calcul doit être figée sans ambiguïté.

**Options envisagées** :
- **Option A — Tarif forfaitaire × prorata journalier (et coefficient pondérateur si véhicule salarié, hors périmètre Floty V1)** : conforme à la notice DGFiP S1 partie IV, ligne N (« porter ici le résultat du produit des colonnes F, J et K ou F, J, K et M »).
- **Option B — Tarif forfaitaire entier dès le 1er jour d'affectation** : non conforme au texte (le prorata est obligatoire).
- **Option C — Tarif forfaitaire si > 6 mois d'affectation, sinon nul** : non conforme.

**Décision retenue** : **Option A — Tarif forfaitaire × Prorata journalier**.

La formule Floty pour la taxe polluants 2024 est :

```
Pour chaque véhicule v de l'entreprise e sur l'année 2024 :
    tarif_forfaitaire_v = catégorie_polluants(v).tarif_2024
                          # 0 € si E ; 100 € si 1 ; 500 € si véhicules les plus polluants
    
    prorata_v = jours_affectation(v, e, 2024) / 366
                # Année bissextile : dénominateur = 366
                # Numérateur : décompte des attributions journalières,
                # diminué des jours d'indisponibilité fourrière publique
                # (cf. taxe-co2/decisions.md Décision 8)
    
    taxe_polluants_v = tarif_forfaitaire_v × prorata_v
```

**Exemple chiffré n°1 — véhicule essence Euro 6 toute l'année 2024**

- Caractéristiques : Peugeot 308 essence Euro 6, immatriculée le 12/05/2022.
- Catégorie polluants : **1** (essence Euro 6, allumage commandé)
- Tarif forfaitaire 2024 : **100 €**
- Affectation : du 01/01/2024 au 31/12/2024, soit 366 jours sur 366
- Prorata : 366 / 366 = 1
- Taxe polluants = 100 × 1 = **100,00 €**

**Exemple chiffré n°2 — véhicule Diesel Euro 6 partiellement affecté**

- Caractéristiques : Renault Trafic Diesel Euro 6, type N1 carrosserie « Camionnette » 2 rangs de places, immatriculé le 03/06/2021.
- Catégorie polluants : **véhicules les plus polluants** (Diesel = allumage par compression, exclu de catégorie 1 même Euro 6)
- Tarif forfaitaire 2024 : **500 €**
- Affectation : du 01/04/2024 au 30/09/2024, soit 183 jours sur 366
- Prorata : 183 / 366 = 0,5
- Taxe polluants = 500 × 0,5 = **250,00 €**

**Exemple chiffré n°3 — véhicule électrique toute l'année 2024**

- Caractéristiques : Tesla Model 3, électrique pur, immatriculée le 14/02/2023.
- Catégorie polluants : **E** (électricité exclusive)
- Tarif forfaitaire 2024 : **0 €**
- Affectation : du 01/01/2024 au 31/12/2024, soit 366 jours sur 366
- Prorata : 366 / 366 = 1
- Taxe polluants = 0 × 1 = **0,00 €** (sans prorata utile)

**Note importante** : pour un véhicule électrique, la taxe polluants est nulle par classement direct en catégorie E (tarif fixé à 0 € par le barème lui-même). Ce n'est PAS une exonération technique au sens du chapitre exonérations — c'est un effet du barème. Cohérent avec CIBS art. L. 421-135.

**Justification** :
- Notice DGFiP S1 partie IV ligne N : « porter ici le résultat du produit des colonnes F, J et K » — soit **F (tarif annuel) × J (proportion d'affectation) × K (% d'affectation à un usage en cas d'usage mixte)**.
- BOFiP S2 § 290 (exemple chiffré, malgré l'écart numérique commenté en Q1 de `recherches.md`) confirme la mécanique multiplicative.
- Cohérence avec la taxe CO₂ (même mécanique de prorata, même règles de détermination du numérateur).

**Niveau de confiance** : **Haute**. Mécanique directement énoncée par les sources primaires.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Le moteur de calcul utilise la même fonction de prorata que pour la taxe CO₂ (d'où l'intérêt de mutualiser le code).
- La table de tarifs polluants en base de données est triviale : 3 lignes pour 2024 — `(catégorie, tarif_2024_eur)` = `(E, 0)`, `(1, 100)`, `(plus_polluants, 500)`.
- Tests unitaires obligatoires : véhicule E toute l'année (= 0 €), véhicule cat. 1 toute l'année (= 100 €), véhicule plus polluants toute l'année (= 500 €), véhicule cat. 1 sur 183 jours = 100 × 183/366 = 50,00 € (avant arrondi total).

---

## Décision 3 — Algorithme de classement d'un véhicule dans une catégorie d'émissions de polluants

**Contexte** : pour chaque véhicule, Floty doit déterminer **sans ambiguïté** sa catégorie d'émissions de polluants (E, 1, ou véhicules les plus polluants), à partir des caractéristiques techniques saisies. Plusieurs options sont possibles selon les données disponibles.

**Options envisagées** :

- **Option A — Classement direct à partir des champs techniques (motorisation + norme Euro)**, par application littérale de l'article L. 421-134 du CIBS.
- **Option B — Classement via la vignette Crit'Air saisie**, en s'appuyant sur la correspondance documentée par BOFiP § 270.
- **Option C — Saisie manuelle de la catégorie fiscale** (E / 1 / plus polluants) sans calcul automatique.

**Décision retenue** : **Option A en règle de référence, Option B en chemin alternatif/de validation, Option C interdite par défaut**.

L'algorithme Floty est :

```
fonction categorie_polluants(vehicule) :
    # 1. Catégorie E (priorité absolue)
    si vehicule.source_energie ∈ {"Électrique", "Hydrogène", "Électrique+Hydrogène"} :
        retourner "E"
    
    # 2. Catégorie 1 — moteur thermique à allumage commandé Euro 5/6
    si vehicule.source_energie ∈ {
            "Essence",
            "Hybride non rechargeable",   # supposé hybride essence — voir note ci-dessous
            "Hybride rechargeable",       # supposé hybride essence — voir note ci-dessous
            "GPL",
            "GNV",
            "Superéthanol E85"
       } ET vehicule.norme_euro ∈ {"Euro 5", "Euro 5a", "Euro 5b",
                                    "Euro 6", "Euro 6b", "Euro 6c",
                                    "Euro 6d-Temp", "Euro 6d", "Euro 6d-ISC", "Euro 6d-ISC-FCM"} :
        retourner "1"
    
    # 3. Catégorie résiduelle — véhicules les plus polluants
    retourner "véhicules les plus polluants"
```

**Cas particuliers gérés par cet algorithme** :
- Diesel Euro 6 → tombe en « véhicules les plus polluants » (puisque Diesel ∉ liste catégorie 1, par exclusion technique : moteur à allumage par compression).
- Essence Euro 4 → « véhicules les plus polluants » (norme < Euro 5).
- Véhicule sans norme Euro renseignée → « véhicules les plus polluants » (par défaut, conforme principe de prudence).

**Cas non géré automatiquement et nécessitant validation** :
- **Hybride Diesel-électrique** (très rare, ex : certains modèles Mercedes Classe E ou Volvo XC90 commercialisés 2018-2022) : si l'utilisateur saisit « Hybride non rechargeable » ou « Hybride rechargeable » avec un moteur thermique sous-jacent Diesel, l'algorithme par défaut le classe en catégorie 1 (faux). **Décision** : enrichir la fiche véhicule d'un champ complémentaire « Type de moteur thermique » (Essence / Diesel) qui se déclenche pour les types « Hybride X ». Si Diesel, classement en « véhicules les plus polluants ». Voir Décision 4 ci-dessous (chemin Crit'Air comme garde-fou).

**Justification** :
- L'**Option A** est la lecture directe et littérale de l'article L. 421-134, 1°-2°-3° du CIBS. Elle s'appuie sur des champs déjà collectés par Floty (cahier des charges § 2.2 : type de carburant, norme Euro).
- L'**Option B** (Crit'Air) reposerait sur une donnée optionnelle (la vignette Crit'Air n'est pas obligatoire pour tous les véhicules — bien qu'elle soit disponible pour la quasi-totalité des véhicules contemporains). Utiliser Crit'Air comme **règle principale** créerait une dépendance à une donnée non garantie. Mais Crit'Air sert de **chemin de validation croisée** : si l'algorithme classe un véhicule en catégorie 1 et que la vignette Crit'Air est 2 ou plus, alerte UI à l'utilisateur (« incohérence apparente entre motorisation/norme Euro et vignette Crit'Air »). Voir Décision 4.
- L'**Option C** (saisie manuelle) reporte la responsabilité de qualification sur l'utilisateur, qui n'a pas la formation fiscale pour la prendre. Inacceptable comme option par défaut.

**Niveau de confiance** : **Haute** sur le tronc principal (E vs 1 vs résiduel pour motorisations classiques). **Moyenne** sur les cas hybrides Diesel-électrique (motorisations rares mais non explicitement traitées par les sources primaires).

**À valider par expert-comptable** : **Oui**, spécifiquement sur le traitement des hybrides Diesel-électrique (point qui sera inscrit dans `incertitudes.md`).

**Conséquences sur l'implémentation** :
- Le champ `type_carburant` du véhicule (déjà prévu cahier des charges § 2.2) est consommé par cette règle.
- Le champ `norme_euro` (déjà prévu cahier des charges § 2.2) est consommé par cette règle.
- Ajout recommandé d'un champ complémentaire conditionnel `type_moteur_thermique_sous_jacent` (Essence / Diesel / sans objet) pour les motorisations « Hybride non rechargeable » et « Hybride rechargeable », afin de distinguer hybride essence (= catégorie 1) vs hybride Diesel (= véhicules les plus polluants).
- L'UI affiche la catégorie polluants calculée et explique le classement (« Catégorie 1 : essence Euro 6 = allumage commandé conforme Euro 5/6 »).
- Tests unitaires : Tesla Model 3 (Électrique → E), Peugeot 308 Essence Euro 6 (→ 1), Renault Clio Diesel Euro 6 (→ plus polluants), Toyota Prius hybride essence Euro 6 (→ 1), Mercedes Classe E 300de hybride Diesel Euro 6 (→ plus polluants), Renault 5 Essence Euro 1 (→ plus polluants).

---

## Décision 4 — Chemin alternatif via vignette Crit'Air et garde-fou de cohérence

**Contexte** : la **vignette Crit'Air** (certificat qualité de l'air, arrêté du 21 juin 2016, art. R. 318-2 code de la route) est en correspondance directe avec la catégorie d'émissions de polluants du CIBS (BOFiP § 270 et § 280). Cette correspondance peut servir soit comme **chemin de saisie alternatif** (l'utilisateur saisit la vignette Crit'Air, Floty calcule la catégorie fiscale), soit comme **garde-fou de cohérence** (validation croisée du classement obtenu par l'algorithme principal).

**Options envisagées** :
- **Option A — Saisie Crit'Air seule** (sans saisie norme Euro) : Floty mappe Crit'Air → catégorie fiscale et déduit. Simple pour l'utilisateur, mais perd la donnée « norme Euro » qui est par ailleurs utile pour la taxe CO₂ et pour la TAI future.
- **Option B — Saisie motorisation + norme Euro UNIQUEMENT** (Décision 3) : algorithme direct, Crit'Air n'est pas demandé.
- **Option C — Saisie complète motorisation + Euro + Crit'Air**, avec algorithme qui s'appuie principalement sur Euro et utilise Crit'Air comme **garde-fou** (alerte UI si incohérence).

**Décision retenue** : **Option C — saisie complète avec Crit'Air en garde-fou**.

**Logique** :
- L'utilisateur saisit motorisation + norme Euro (obligatoire) + vignette Crit'Air (recommandé, marqué « optionnel mais conseillé pour vérification »).
- Floty calcule la **catégorie fiscale** par l'algorithme de la Décision 3 (priorité à la motorisation + norme Euro, qui sont des données de carte grise et donc fiables).
- Si Crit'Air est saisie, Floty applique la table de correspondance suivante et **vérifie la cohérence** :

| Vignette Crit'Air saisie | Catégorie fiscale attendue |
|---|---|
| Crit'Air E (verte) | E |
| Crit'Air 1 (violette) | 1 |
| Crit'Air 2 | véhicules les plus polluants |
| Crit'Air 3 | véhicules les plus polluants |
| Crit'Air 4 | véhicules les plus polluants |
| Crit'Air 5 | véhicules les plus polluants |
| Non classé / Pas de vignette | véhicules les plus polluants |

- Si la catégorie fiscale calculée par l'algorithme **diffère** de la catégorie fiscale **attendue** d'après la vignette Crit'Air saisie, Floty affiche un avertissement UI : « Incohérence apparente entre motorisation/norme Euro et vignette Crit'Air. Vérifiez les données du certificat d'immatriculation et du certificat qualité de l'air. Floty utilise le résultat de l'algorithme motorisation/Euro (XYZ) sauf modification manuelle. »

**Justification** :
- La motorisation et la norme Euro sont des données du **certificat d'immatriculation** (cases P.3 « source d'énergie » et V.9 « norme Euro » sur les cartes grises récentes), donc fiables et obligatoires.
- La vignette Crit'Air est un **certificat distinct** (acheté séparément), peut être manquant ou erroné en pratique. Mais quand elle est présente et cohérente, elle valide l'algorithme.
- Le BOFiP § 270 établit explicitement la correspondance Crit'Air ↔ catégorie CIBS, ce qui légitime l'usage de Crit'Air comme garde-fou.
- L'avertissement UI plutôt qu'un blocage permet à l'utilisateur de tracer une incohérence (cas exotiques : véhicule importé avec norme étrangère mal mappée, vignette achetée avant changement de motorisation, etc.) sans bloquer le calcul.

**Niveau de confiance** : **Moyenne**. La correspondance Crit'Air ↔ catégorie fiscale est solidement documentée par le BOFiP, mais le mécanisme de garde-fou est un **choix produit** dont la mise en œuvre concrète (seuils d'alerte, message exact, traçabilité de la décision retenue) reste à affiner avec Renaud.

**À valider par expert-comptable** : Non sur le principe (corrélation Crit'Air ↔ catégorie fiscale est doctrinale BOFiP). À discuter avec Renaud sur l'UX de l'avertissement.

**Conséquences sur l'implémentation** :
- Champ optionnel `vignette_critair` dans la fiche véhicule, valeurs : « E », « 1 », « 2 », « 3 », « 4 », « 5 », « Non classé », « Non saisi ».
- Fonction de validation croisée appelée à la création/modification d'un véhicule.
- Affichage UI de la catégorie fiscale retenue avec sa justification courte (« Essence Euro 6 → catégorie 1 ; cohérent avec vignette Crit'Air 1 saisie »).
- Dans le PDF récapitulatif : afficher la catégorie polluants avec sa justification (cf. Décision 1 et report Décision 7 du sous-dossier taxe-co2).

---

## Décision 5 — Exemples chiffrés de référence pour les tests unitaires Floty

**Contexte** : pour valider l'implémentation du moteur de calcul Floty, il faut un jeu d'exemples chiffrés de référence couvrant les trois catégories tarifaires et les cas de prorata standard. Ces exemples seront repris en tests unitaires automatisés et dans le `taxes-rules/2024.md`.

**Décision retenue** : retenir le jeu suivant, calculable à la main et reproductible.

**Hypothèse commune à tous les exemples** : année 2024 (366 jours), entreprise utilisatrice unique, pas de coefficient pondérateur (cas hors véhicule salarié).

### Exemple A — Catégorie E, affectation toute l'année

- Véhicule : Tesla Model 3 (M1 électrique pur)
- Affectation : 01/01/2024 → 31/12/2024 = 366 jours
- Prorata : 366 / 366 = 1,0
- Tarif annuel plein 2024 : 0 €
- **Taxe polluants = 0 × 1 = 0,00 €**

### Exemple B — Catégorie 1, affectation toute l'année

- Véhicule : Peugeot 308 PureTech 130 ch essence Euro 6d-ISC-FCM (M1, allumage commandé)
- Affectation : 01/01/2024 → 31/12/2024 = 366 jours
- Prorata : 1,0
- Tarif annuel plein 2024 : 100 €
- **Taxe polluants = 100 × 1 = 100,00 €**

### Exemple C — Catégorie 1, affectation partielle (cas BOFiP-like)

- Véhicule : Toyota Corolla hybride essence (M1, hybride essence — donc allumage commandé), Euro 6
- Affectation : 01/03/2024 → 31/12/2024 = 306 jours
- Prorata : 306 / 366 ≈ 0,83607
- Tarif annuel plein : 100 €
- **Taxe polluants = 100 × 306/366 = 83,6065… € ≈ 83,61 € (avant arrondi total final)**

### Exemple D — Véhicules les plus polluants, affectation toute l'année

- Véhicule : Renault Trafic Diesel Euro 6 (N1 « Camionnette » 2 rangs de places, transport personnes — donc dans le champ ; Diesel = allumage par compression → catégorie résiduelle)
- Affectation : 01/01/2024 → 31/12/2024 = 366 jours
- Prorata : 1,0
- Tarif annuel plein : 500 €
- **Taxe polluants = 500 × 1 = 500,00 €**

### Exemple E — Véhicules les plus polluants, affectation partielle

> **Correction du 2026-04-24 suite à l'audit rapport-001** : la version initiale de cet exemple utilisait 30 jours d'affectation, ce qui entraînait l'exonération LCD automatique après la résolution de Z-2024-002 (cf. R-2024-021, cumul annuel par couple ≤ 30 jours). L'exemple est désormais reformulé avec 60 jours pour éviter toute ambiguïté avec le seuil LCD.

- Véhicule : BMW Série 5 Diesel Euro 6 (M1)
- Affectation : 15/06/2024 → 13/08/2024 = 60 jours (cumul annuel > 30 jours → exonération LCD R-2024-021 non applicable → couple taxable)
- Prorata : 60 / 366 ≈ 0,16393
- Tarif annuel plein : 500 €
- **Taxe polluants = 500 × 60/366 = 81,9672… € ≈ 81,97 € (avant arrondi total final)**

### Exemple F — Combinaison taxe polluants + taxe CO₂ pour un véhicule

(Validation que Floty produit bien le total agrégé par véhicule.)

- Véhicule : Renault Mégane essence Euro 6 (M1, méthode WLTP, CO₂ WLTP = 130 g/km), 1ère immat. en France 15/03/2022
- Affectation : 01/01/2024 → 31/12/2024 = 366 jours, prorata 1,0
- Taxe CO₂ (cf. `2024/taxe-co2/decisions.md` Décision 4 — calcul WLTP 130 g/km) : 383 € (tarif annuel plein) × 1 = **383,00 €**
- Taxe polluants : essence Euro 6 = catégorie 1 → 100 € × 1 = **100,00 €**
- **Total véhicule = 383 + 100 = 483,00 €** (avant arrondi total final qui s'applique sur l'ensemble du redevable)

### Exemple G — Année bissextile, validation du dénominateur

- Véhicule : Skoda Octavia essence Euro 6, M1
- Affectation : exactement la moitié de l'année 2024 = 183 jours (par exemple 01/01/2024 → 01/07/2024)
- Prorata : 183 / 366 = **exactement 0,5**
- Taxe polluants = 100 × 0,5 = **50,00 €**

(Si l'on appliquait par erreur 365 jours au dénominateur, on aurait 100 × 183/365 = 50,137 € — différence détectable au niveau de l'arrondi total.)

**Justification** :
- Exemples couvrant les 3 catégories tarifaires (E, 1, plus polluants) et plusieurs cas de prorata.
- Validation explicite du dénominateur 366 (Exemple G).
- Vérification de la combinaison CO₂ + polluants pour un véhicule (Exemple F).
- Identification initiale d'un cas de prudence sur la qualification LLD/LCD (Exemple E — note explicite). *Mise à jour : Z-2024-002 a été résolue le 23/04/2026 — la lecture définitive est l'application systématique de l'exonération LCD avec cumul annuel par couple ; voir `taxes-rules/2024.md` R-2024-021.*

**Niveau de confiance** : **Haute** sur les calculs (purement arithmétiques à partir des règles fixées). L'Exemple E reste valable comme illustration pédagogique, mais son interprétation a été clarifiée par la résolution de Z-2024-002.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Suite de tests unitaires Floty pour la taxe polluants 2024 : 7 cas (A à G) au minimum, plus tests aux bornes (catégorie d'un Diesel Euro 5, d'un essence Euro 4, d'un véhicule sans norme Euro renseignée).
- Documentation utilisateur : ces exemples sont reproductibles dans l'application via la création d'un véhicule et d'une attribution avec les données indiquées.

---

## Décision 6 — Sources primaires retenues pour la traçabilité de la taxe polluants 2024

**Contexte** : la méthodologie projet (§ 4.1) impose que toute valeur numérique soit tracée à une source primaire. Il convient de désigner explicitement les sources primaires utilisées pour la taxe polluants 2024. Cette décision est l'**équivalent strict** de la Décision 10 du sous-dossier `2024/taxe-co2/`, adaptée à la bibliographie propre à la taxe polluants.

**Décision retenue** : trois sources primaires concordantes sont retenues, dans l'ordre d'autorité légale :

1. **Texte de loi — Code des Impositions sur les Biens et Services (CIBS), articles L. 421-133, L. 421-134 et L. 421-135** (Paragraphe 4 « Tarifs de la taxe annuelle sur les émissions de polluants atmosphériques des véhicules de tourisme »), dans leur version applicable au 31 décembre 2023 (modifiée par la loi n° 2023-1322 du 29 décembre 2023, art. 97 — loi de finances pour 2024). Source d'autorité légale, fait foi devant l'administration fiscale.
2. **Doctrine officielle — Bulletin Officiel des Finances Publiques (BOFiP-Impôts)**, identifiants `BOI-AIS-MOB-10-30-20-20240710` (taxes d'affectation des véhicules de tourisme — section IV « Montant de la taxe annuelle sur les émissions de polluants atmosphériques » §§ 260-290) et `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes aux taxes d'affectation, transversales CO₂ + polluants). Doctrine fiscale opposable à l'administration.
3. **Notice administrative — Notice DGFiP n° 2858-FC-NOT-SD (Cerfa 52375#03)** dans sa version applicable à la déclaration des taxes 2024 (édition décembre 2024). Reproduit textuellement le tableau des trois tarifs forfaitaires et les définitions des trois catégories d'émissions.

**Note importante** : la notice DGFiP propre à la taxe polluants est **n° 2858-FC-NOT-SD**, distincte de la notice n° **2857-FC-NOT-SD** qui couvre la taxe CO₂. Les deux notices ont des Cerfa distincts (52375#03 pour polluants, 52374#03 pour CO₂) bien qu'elles se déclarent sur les mêmes formulaires (annexe 3310 A ou formulaire 3517).

**Justification** :
- Cette triangulation garantit qu'aucune valeur numérique ne repose sur une source unique.
- Les **trois tarifs forfaitaires 2024** (E = 0 €, catégorie 1 = 100 €, véhicules les plus polluants = 500 €) sont mentionnés à l'identique par les trois sources primaires (CIBS, BOFiP § 280, notice DGFiP partie IV ligne F), et confirmés par 4 sources tertiaires indépendantes (PwC, FNA, Compta-Online, Guichet Carte Grise).
- La **définition des trois catégories** est mentionnée à l'identique par CIBS L. 421-134 et notice DGFiP partie IV ligne F (texte technique « moteur thermique à allumage commandé »), et **paraphrasée fonctionnellement équivalente** par le BOFiP § 260 (« essence, hybrides et gaz »). L'équivalence est documentée et validée (cf. `recherches.md` § 6.2).
- Les sources tertiaires (S6 à S11 de `sources.md`) ont été consultées pour valider l'interprétation pratique mais ne sont pas autoritaires en cas de divergence.

**Niveau de confiance** : **Haute** (triangulation primaire complète, cohérence inter-sources sur tous les points numériques, équivalence des deux formulations CIBS vs BOFiP démontrée).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Les références S1 (notice DGFiP 2858), S2 (BOFiP § 260-290), S3 (BOFiP commun) et S4 (CIBS art. L. 421-133 à L. 421-144) de `sources.md` sont les sources de vérité pour les valeurs numériques implémentées dans le moteur de calcul Floty.
- Toute mise à jour du barème (entrée en vigueur LF 2026 au 01/01/2026) sera tracée dans le sous-dossier `2026/taxe-polluants/` avec sa propre triangulation primaire.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Décisions initiales taxe polluants 2024 — 6 décisions documentées (report dispositions communes, mécanique forfaitaire × prorata, algorithme classement par motorisation+Euro, garde-fou Crit'Air, exemples chiffrés de référence A-G, sources primaires). 5 décisions à confiance haute, 1 à confiance moyenne (Décision 4 — choix produit Crit'Air). |

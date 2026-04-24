# Décisions — Cas particuliers et règles transitoires applicables aux taxes annuelles CO₂ et polluants — Exercice 2024

> **Statut** : Version 0.1
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 22 avril 2026
> **Renvoi sources** : voir `sources.md` (cotes S1, S2, …) et `recherches.md` pour les analyses détaillées.

---

## Décision 1 — Algorithme de qualification du « type fiscal » d'un véhicule (frontière M1 / N1)

**Contexte** : la qualification d'un véhicule comme « véhicule de tourisme » au sens fiscal (CIBS art. L. 421-2) détermine son entrée dans le champ des deux taxes annuelles. Cette qualification repose sur une combinaison de critères : catégorie de réception européenne (M1 / N1), libellé de carrosserie (rubrique J.2 du certificat d'immatriculation), nombre de places assises (rubrique S.1), et — pour les camionnettes N1 — affectation au transport de personnes. La règle est documentée par l'article L. 421-2 du CIBS et précisée par BOFiP `BOI-AIS-MOB-10-30-20` § 60. Voir `recherches.md` § 3.

**Options envisagées** :

- **Option A — Champ « type_fiscal » saisi manuellement par l'utilisateur** : laisser l'utilisateur indiquer si le véhicule est taxable ou non. Risque d'erreur élevé, l'utilisateur n'ayant pas la formation fiscale.
- **Option B — Champ « type_fiscal » calculé automatiquement à partir des critères techniques de la carte grise**, avec garde-fou UI pour les cas frontières. Conforme aux principes de Floty (« machine à produire des déclarations fiscales justes avec le minimum de saisie » — cahier des charges § 1.2).
- **Option C — Saisie « VP » / « VU » directement sans qualification fiscale** : suit la nomenclature usuelle française (voiture particulière / véhicule utilitaire), ce qui est imprécis vis-à-vis de la frontière fiscale CIBS L. 421-2.

**Décision retenue** : **Option B — qualification fiscale automatique par algorithme, avec garde-fou UI pour les cas frontières**.

L'algorithme Floty est :

```
fonction qualifier_type_fiscal(vehicule) :
    # Cas 1 — M1 standard
    si vehicule.categorie_reception_europeenne == "M1" :
        si vehicule.usage_special == True :
            retourner "non_taxable"   # ambulance, corbillard, blindé, etc.
        sinon :
            retourner "taxable"
    
    # Cas 2 — N1 pick-up
    si vehicule.categorie_reception_europeenne == "N1"
       ET vehicule.carrosserie == "Camion pick-up" :
        si vehicule.nombre_places_assises >= 5
           ET NON vehicule.usage_remontees_mecaniques_skiables :
            retourner "taxable"
        sinon :
            retourner "non_taxable"
    
    # Cas 3 — N1 camionnette
    si vehicule.categorie_reception_europeenne == "N1"
       ET vehicule.carrosserie == "Camionnette" :
        si vehicule.nombre_places_assises >= 5
           OU vehicule.banquette_amovible_avec_2_rangs == True :
            si vehicule.affectation_transport_personnes == True :
                retourner "taxable"
            sinon :
                retourner "non_taxable"
        sinon :
            retourner "non_taxable"
    
    # Cas 4 — Tous les autres cas (M2, M3, N2, N3, etc.)
    retourner "hors_perimetre_floty"
```

**Justification** :

- Conforme à la lettre de l'article L. 421-2 du CIBS (texte intégral consulté sur Légifrance — S1).
- Conforme à la doctrine BOFiP § 60 (S4) qui détaille les critères opérationnels (rubriques J.1, J.2, S.1).
- Conforme au principe Floty de minimisation de la saisie (cahier des charges § 1.2) : l'utilisateur saisit les caractéristiques techniques de la carte grise, Floty calcule la qualification fiscale.
- Le garde-fou UI sur les cas frontières (pick-up exactement 5 places, camionnette avec ou sans transport de personnes) permet à l'utilisateur de confirmer la qualification dans les cas où une interprétation est nécessaire.

**Niveau de confiance** : **Haute** (lecture directe et univoque de l'article CIBS et de la doctrine BOFiP).

**À valider par expert-comptable** : Non sur le principe (lecture textuelle directe).

**Conséquences sur l'implémentation** :

- Les champs Floty suivants doivent être présents dans la fiche véhicule (`recherches.md` § 3.5) :
  - `categorie_reception_europeenne` (énumération M1/N1/...) — obligatoire ;
  - `carrosserie` (texte) — obligatoire pour N1 ;
  - `nombre_places_assises` (entier) — obligatoire ;
  - `usage_special` (booléen) — pour M1 ;
  - `usage_remontees_mecaniques_skiables` (booléen) — pour pick-ups N1 ≥ 5 places ;
  - `banquette_amovible_avec_2_rangs` (booléen) — pour camionnettes N1 ;
  - `affectation_transport_personnes` (booléen) — pour camionnettes N1 ≥ 2 rangs.
- Le champ calculé `type_fiscal` (énumération `taxable` / `non_taxable` / `hors_perimetre_floty`) est dérivé de l'algorithme ci-dessus.
- L'UI Floty affiche la qualification calculée avec sa justification courte (ex : « Taxable — pick-up N1 avec 5 places assises (CIBS art. L. 421-2, 2°-a) »).
- Garde-fou UI : voir `recherches.md` § 3.5.
- Le moteur de calcul ne calcule la taxe que pour les véhicules `type_fiscal == "taxable"`.

**Mise à jour proposée du cahier des charges § 2.1** : harmoniser le critère « camionnettes ≥ 3 rangs de places » avec la lettre du CIBS (« au moins deux rangs de places ») et ajouter le critère « affecté au transport de personnes ». Cette mise à jour sera proposée à Renaud avec un commit dédié.

---

## Décision 2 — Algorithme de détermination du barème pour les véhicules importés d'occasion

**Contexte** : pour un véhicule homologué selon une méthode (WLTP ou NEDC) à l'étranger et immatriculé pour la première fois en France ultérieurement, la règle de bascule entre WLTP, NEDC et PA s'appuie sur la **méthode d'homologation effective** (qui figure normalement sur le certificat de conformité COC), et non uniquement sur la date de 1ère immatriculation en France. Cette lecture est confirmée par la lettre de l'article L. 421-119-1 du CIBS, par la doctrine BOFiP § 210-220, et par la formulation de la notice DGFiP partie II.1. Voir `recherches.md` § 4.

**Options envisagées** :

- **Option A — Date France seule** : appliquer WLTP si 1ère immat. France ≥ 01/03/2020, NEDC sinon. Lecture simplifiée mais incorrecte pour les véhicules importés d'occasion (un véhicule homologué NEDC à l'étranger en 2018 et immatriculé en France en 2022 serait à tort calculé sur le barème WLTP).
- **Option B — Méthode d'homologation effective** : appliquer le barème correspondant à la méthode selon laquelle les émissions de CO₂ ont été mesurées à l'origine (WLTP ou NEDC), conformément à la lettre du 1° et 2° de l'article L. 421-119-1.

**Décision retenue** : **Option B — méthode d'homologation effective**.

L'algorithme étendu (par rapport à `2024/taxe-co2/decisions.md` Décision 1) est :

```
fonction determiner_bareme(vehicule) :
    # Priorité 1 — Méthode d'homologation effective
    si vehicule.methode_homologation == "WLTP" ET vehicule.co2_wltp est renseigné :
        retourner "WLTP"
    sinon si vehicule.methode_homologation == "NEDC" ET vehicule.co2_nedc est renseigné :
        # Vérifier la condition du 2° L. 421-119-1
        si vehicule.date_premiere_immatriculation_origine >= 2004-06-01
           ET vehicule.date_premiere_affectation_par_entreprise >= 2006-01-01 :
            retourner "NEDC"
        sinon :
            retourner "PA"
    sinon :
        retourner "PA"
```

**Note clé** : la `date_premiere_immatriculation_origine` (étrangère pour un véhicule importé) est utilisée pour la condition du 2°, **et non** la `date_premiere_immatriculation_france`. Cette précision résout l'incertitude Z-2024-004.

**Justification** :

- Le 1° de l'article L. 421-119-1 utilise la formulation « véhicules immatriculés en recourant à la méthode de détermination des émissions de dioxyde de carbone dite WLTP » — c'est une condition sur la **méthode**, pas sur la date France. Lecture littérale (S1, art. L. 421-119-1).
- Le 2° utilise « ayant fait l'objet d'une réception européenne, immatriculés pour la première fois à compter du 1er juin 2004 » — sans préciser « en France » : la date de 1ère immat. peut être à l'étranger (toute réception européenne).
- La doctrine BOFiP § 210-220 (S4) confirme cette lecture par méthode d'homologation.
- La date du 01/03/2020 mentionnée par la notice DGFiP S7 partie II.1 est un **critère pratique de bascule** propre à la France (date à laquelle tous les véhicules neufs immatriculés en France l'étaient en méthode WLTP), et non un critère normatif de fond.
- Conforme au principe de prudence et de respect du texte (méthodologie § 8.3 — « la lecture la plus majorante » n'est pas en jeu ici, c'est une lecture textuelle directe).

**Niveau de confiance** : **Haute** (triangulation primaire complète : article CIBS + doctrine BOFiP + cohérence avec la notice DGFiP).

**À valider par expert-comptable** : Non (lecture textuelle directe).

**Conséquences sur l'implémentation** :

- La fiche véhicule Floty doit collecter (en plus des champs déjà documentés dans `2024/taxe-co2/decisions.md` Décision 1) :
  - `date_premiere_immatriculation_origine` (date) — date de 1ère immat. à l'étranger pour les véhicules importés ; égale à `date_premiere_immatriculation_france` pour les véhicules acquis neufs en France.
  - Marqueur ou indicateur dérivé `vehicule_importe_d_occasion` (booléen calculé : true si les deux dates diffèrent).
- L'algorithme `determiner_bareme` étendu est appliqué.
- L'UI affiche la justification du barème retenu (« NEDC appliqué — méthode d'homologation effective à l'étranger en 2018 conformément à CIBS art. L. 421-119-1, 2° »).
- Garde-fou UI pour les véhicules importés : « Ce véhicule a été homologué [WLTP/NEDC] à l'étranger le [date origine]. Le barème [WLTP/NEDC/PA] est appliqué conformément à la méthode d'homologation effective (CIBS art. L. 421-119-1). »
- Test unitaire impératif : Cas B.2 de `recherches.md` § 4.6 (BMW Série 3 NEDC importée → barème NEDC).

---

## Décision 3 — Bascule automatique sur barème PA en cas de donnée CO₂ manquante (point UX)

**Contexte** : l'article L. 421-119-1, 3° du CIBS prévoit explicitement que les véhicules dont les émissions de CO₂ « n'ont pas pu être déterminées » basculent sur le barème PA. La règle juridique est claire ; la problématique pour Floty est essentiellement **UX/produit** : un utilisateur peut saisir un véhicule sans donnée CO₂ pour des raisons diverses, et Floty doit gérer ce cas avec transparence. Voir `recherches.md` § 5.

**Options envisagées** :

- **Option A — Bloquer le calcul si donnée CO₂ manquante** : forcer l'utilisateur à compléter la fiche véhicule. Strict mais peu pratique (notamment si la donnée CO₂ n'est durablement pas disponible — véhicule très ancien, importé sans COC).
- **Option B — Bascule automatique sur PA avec alerte UI claire** : appliquer la règle juridique (bascule sur PA), informer l'utilisateur de la bascule et l'inviter à compléter si possible.
- **Option C — Bascule silencieuse sur PA** : appliquer la règle sans alerter l'utilisateur. Risque d'incompréhension (le tarif PA est très élevé par rapport au tarif WLTP/NEDC).

**Décision retenue** : **Option B — bascule automatique sur PA avec alerte UI claire**.

L'algorithme Floty étendu :

```
fonction determiner_bareme_avec_bascule_pa(vehicule) :
    # Cas nominal — CO₂ disponible selon la méthode d'homologation
    si vehicule.methode_homologation == "WLTP" ET vehicule.co2_wltp est renseigné :
        retourner ("WLTP", "")
    sinon si vehicule.methode_homologation == "NEDC" ET vehicule.co2_nedc est renseigné :
        retourner ("NEDC", "")
    
    # Cas de bascule — donnée CO₂ manquante
    si vehicule.puissance_administrative est renseigné :
        retourner ("PA", "Donnée CO₂ manquante — bascule automatique sur barème puissance administrative (CIBS art. L. 421-119-1, 3°)")
    
    # Cas d'erreur de saisie — ni CO₂ ni PA disponible
    retourner ("ERREUR", "Aucune donnée d'émissions ni de puissance administrative disponible. Impossible de calculer la taxe CO₂. Veuillez compléter la fiche véhicule.")
```

**Justification** :

- Conforme à la règle juridique explicite (CIBS art. L. 421-119-1, 3° + BOFiP § 220 + notice DGFiP partie II.1).
- L'alerte UI répond à un enjeu d'**auditabilité** : l'expert-comptable et l'utilisateur doivent pouvoir comprendre pourquoi le tarif est élevé sur ce véhicule.
- L'écart numérique entre tarif PA et tarif WLTP est généralement **massif** (de l'ordre de × 30 à × 100 pour un véhicule de gamme moyenne), ce qui crée une incitation forte à corriger la saisie si la donnée CO₂ devient disponible.
- L'option « bloquante » (A) est inappropriée car certains véhicules basculent **légitimement** sur PA (véhicules anciens sans réception européenne, par exemple).
- L'option « silencieuse » (C) est inacceptable au regard du principe de transparence (méthodologie § 8.1).

**Niveau de confiance** : **Haute** (règle juridique explicite + bonne pratique UX).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :

- Le moteur de calcul Floty applique la bascule automatique sur PA en cas de donnée CO₂ manquante.
- La fiche déclaration affiche un encart d'alerte non-bloquant : « Donnée CO₂ manquante pour ce véhicule — bascule automatique sur barème puissance administrative. Si la valeur CO₂ devient disponible, mettez à jour la fiche véhicule pour basculer sur le barème WLTP/NEDC. »
- Le PDF récapitulatif mentionne la bascule sur la ligne du véhicule : « Barème PA appliqué — donnée CO₂ manquante ».
- Cas d'erreur (ni CO₂ ni PA) : message d'erreur **bloquant** pour la déclaration, invitant l'utilisateur à compléter la fiche véhicule.
- Test unitaire : véhicule WLTP-éligible (1ère immat. France 15/06/2022) sans CO₂ saisi, avec PA = 6 CV → bascule sur PA → tarif annuel plein 11 250 € (cf. `recherches.md` § 5.4).

---

## Décision 4 — Algorithme de classement étendu pour les hybrides Diesel-électrique (catégorie polluants)

**Contexte** : un véhicule hybride combinant un moteur Diesel et un moteur électrique ne satisfait pas la condition « alimenté par un moteur thermique à allumage commandé » de l'article L. 421-134, 2° (le Diesel utilise l'allumage par compression). Lecture stricte du texte → catégorie « véhicules les plus polluants » (500 € en 2024). Voir `recherches.md` § 6.

**Options envisagées** :

- **Option A — Lecture stricte de l'article L. 421-134** : un hybride Diesel-électrique n'a pas d'allumage commandé → catégorie « véhicules les plus polluants ». Prudent et conforme à la lettre du texte.
- **Option B — Lecture extensive** : assimiler l'hybride Diesel à un hybride essence par cohérence d'esprit (les deux ont une motorisation à faibles émissions). Mais incompatible avec la lettre du texte et avec la cohérence Crit'Air (un hybride Diesel a Crit'Air 2, pas 1).
- **Option C — Saisie manuelle de la catégorie polluants par l'utilisateur** : reporte la responsabilité sur l'utilisateur, qui n'a pas la formation fiscale.

**Décision retenue** : **Option A — lecture stricte, classement en « véhicules les plus polluants »**.

Algorithme étendu (par rapport à `2024/taxe-polluants/decisions.md` Décision 3) :

```
fonction categorie_polluants_etendu(vehicule) :
    # 1. Catégorie E (priorité absolue)
    si vehicule.source_energie ∈ {"Électrique", "Hydrogène", "Électrique+Hydrogène"} :
        retourner "E"
    
    # 2. Catégorie 1 — moteur thermique à allumage commandé Euro 5/6 (mono-carburant)
    si vehicule.source_energie ∈ {"Essence", "GPL", "GNV", "Superéthanol E85"}
       ET vehicule.norme_euro ∈ {Euro 5, ..., Euro 6d-ISC-FCM} :
        retourner "1"
    
    # 3. Cas hybride — désambiguïsation par le moteur thermique sous-jacent
    si vehicule.source_energie ∈ {"Hybride non rechargeable", "Hybride rechargeable"} :
        si vehicule.type_moteur_thermique_sous_jacent == "Essence"
           ET vehicule.norme_euro ∈ {Euro 5, ..., Euro 6d-ISC-FCM} :
            retourner "1"
        sinon si vehicule.type_moteur_thermique_sous_jacent == "Diesel" :
            retourner "véhicules les plus polluants"
        sinon :
            retourner "véhicules les plus polluants"   # par défaut, prudence
    
    # 4. Catégorie résiduelle (Diesel pur, essence pré-Euro 5, etc.)
    retourner "véhicules les plus polluants"
```

**Justification** :

- La consigne explicite de la mission impose la lecture littérale de l'article CIBS (cf. § « IMPORTANT — Leçons des phases précédentes » du brief de mission).
- L'absence d'allumage commandé pour un moteur Diesel est une réalité technique objective et univoque (le Diesel utilise l'allumage par compression).
- La lecture stricte est cohérente avec la **vignette Crit'Air** : un hybride Diesel a Crit'Air 2 (pas 1), ce qui correspond à la catégorie « véhicules les plus polluants » de la table de correspondance BOFiP § 270 (S4).
- Aucune source primaire ni secondaire ne prévoit d'assouplissement pour les hybrides Diesel (`recherches.md` § 6.3).
- Application du principe de prudence (méthodologie § 8.3) en cas de doute.
- Cohérent avec la Décision 9 de `2024/exonerations/decisions.md` (les hybrides Diesel-électrique sont exclus de l'exonération hybride § L. 421-125).

**Niveau de confiance** : **Moyenne**. La lecture est textuellement défendable mais aucune source primaire ne traite **explicitement** ce cas. Une validation expert-comptable reste souhaitable.

**À valider par expert-comptable** : **Oui** (validation souhaitable bien que la lecture soit textuellement directe).

**Conséquences sur l'implémentation** :

- Ajout d'un champ Floty `type_moteur_thermique_sous_jacent` (énumération {Essence, Diesel, sans objet}), conditionnel pour les motorisations « Hybride non rechargeable » et « Hybride rechargeable ». Sans cette donnée, le moteur de calcul ne peut pas qualifier correctement la catégorie polluants.
- L'UI affiche un sélecteur obligatoire pour ce champ lors de la saisie d'un véhicule hybride, avec une explication contextuelle.
- L'algorithme `categorie_polluants_etendu` est appliqué par le moteur de calcul.
- Test unitaire : Mercedes Classe E 300de hybride rechargeable Diesel + électrique → catégorie « véhicules les plus polluants » → 500 € (cf. `recherches.md` § 6.6).
- Le garde-fou de cohérence Crit'Air (cf. `2024/taxe-polluants/decisions.md` Décision 4) confirme la qualification : un hybride Diesel saisi comme catégorie 1 par erreur sera détecté par incohérence avec Crit'Air 2.

---

## Décision 5 — Continuité de l'incertitude Z-2024-001 (indisponibilités) et clôture de Z-2024-002 (LCD)

> **Note d'historique** : cette décision a été initialement rédigée le 22/04/2026 pour consolider **deux** incertitudes ouvertes (Z-2024-001 et Z-2024-002) sans les résoudre. Le 23/04/2026, **Z-2024-002 a été résolue** par clarification directe avec le client (lecture définitive : LCD avec cumul annuel par couple — voir `2024/exonerations/decisions.md` Décision 7 révisée et `taxes-rules/2024.md` R-2024-021). Seule Z-2024-001 reste concernée par une consolidation sans résolution.

**Contexte** : les incertitudes Z-2024-001 (indisponibilités longues hors fourrière) et Z-2024-002 (qualification LLD/LCD) ont été ouvertes par les sous-dossiers précédents. Le présent sous-dossier les consolide en règles d'implémentation Floty. Voir `recherches.md` § 7 (LCD — désormais résolue) et § 8 (indisponibilités).

**Décision retenue pour Z-2024-001 (indisponibilités — maintenue ouverte)** :

- Règle Floty : seul le type d'indisponibilité « Fourrière / immobilisation administrative » réduit le numérateur du prorata (`2024/taxe-co2/decisions.md` Décision 8).
- Garde-fou UI : indicateur visible lors de la saisie de l'indisponibilité (« cette indisponibilité réduira / ne réduira pas le prorata fiscal »).
- Champ Floty `indisponibilite.type` (déjà prévu cahier des charges § 2.5).
- Statut de Z-2024-001 : **Ouvert** (conserve le statut), avec mention « Désormais documenté en règle d'implémentation Floty dans `2024/cas-particuliers/recherches.md` § 8 ».
- **Niveau de confiance** : **Moyenne** (lecture stricte du BOFiP S5 § 190, mais validation expert-comptable reste souhaitable).

**Décision révisée pour Z-2024-002 (LCD — résolue 23/04/2026)** :

- Règle Floty : application systématique de l'exonération LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) — voir `2024/exonerations/decisions.md` Décision 7 (révisée) et `taxes-rules/2024.md` R-2024-021.
- Les champs Floty initialement proposés par cette décision (`entreprise_utilisatrice.qualification_mise_a_disposition`, `attribution.qualification_specifique`) sont **abandonnés** : le moteur applique systématiquement la mécanique de cumul et il n'y a plus de qualification manuelle à effectuer.
- Exigence UI consécutive : la vue par entreprise affiche un compteur du cumul annuel et l'impact fiscal estimé par couple (cahier des charges § 3.4 v1.4).
- Statut de Z-2024-002 : **Résolu — 23/04/2026**.
- **Niveau de confiance** : **Haute** (lecture conforme à la doctrine, validée par plusieurs années de pratique de Renaud sans redressement fiscal).

**À valider par expert-comptable** : Oui pour Z-2024-001 ; non pour Z-2024-002 (résolu par clarification client).

**Conséquences sur l'implémentation** :

- Pour Z-2024-001 : aucune modification par rapport à la Décision 8 de `2024/taxe-co2/`. La consolidation de la présente décision porte uniquement sur la documentation.
- Pour Z-2024-002 : révision structurante. La règle R-2024-021 dans `taxes-rules/2024.md` intègre la mécanique de cumul par couple et devient la règle standard par défaut (plus de qualification manuelle). Les références documentaires initiales à « qualification LLD par défaut » sont toutes remplacées par « exonération LCD avec cumul annuel par couple ».

---

## Décision 6 — Exigence produit : historisation des caractéristiques fiscales du véhicule

**Contexte** : un véhicule peut changer de caractéristiques fiscales en cours d'année (conversion E85, modification d'aménagement handicap, retrait d'homologation, etc.). La lecture par défaut retenue (`recherches.md` § 10) est d'appliquer les caractéristiques effectives à chaque jour d'affectation. Cette exigence requiert que Floty puisse historiser les versions successives des caractéristiques fiscales d'un véhicule.

Cette exigence dépasse le périmètre strict de la recherche fiscale : elle relève de la **conception applicative**. Elle est documentée ici comme proposition de mise à jour du cahier des charges.

**Options envisagées** :

- **Option A — Pas d'historisation** : Floty stocke uniquement les caractéristiques fiscales courantes du véhicule. Une modification écrase l'ancienne version. Simplification ; mais incorrect dès qu'un véhicule change de caractéristiques en cours d'année (risque de calcul erroné rétroactif).
- **Option B — Historisation complète** : Floty stocke chaque version des caractéristiques fiscales avec sa date d'effet. Le moteur de calcul lit la version effective à chaque jour d'affectation. Correct mais complexité accrue.
- **Option C — Historisation à la demande** : Floty enregistre une nouvelle version uniquement si l'utilisateur le demande explicitement (case à cocher « cette modification correspond à un changement de caractéristiques en cours d'année »). Compromis pragmatique.

**Décision retenue** : **Option B — historisation complète des caractéristiques fiscales**.

Modèle de données proposé :

| Table | Champs | Rôle |
|---|---|---|
| `vehicule_caracteristiques_fiscales` | `vehicule_id`, `date_effet`, `date_fin_effet` (nullable), `source_energie`, `co2_wltp`, `co2_nedc`, `puissance_administrative`, `norme_euro`, `categorie_polluants`, `methode_homologation`, `type_moteur_thermique_sous_jacent`, `vehicule_accessible_fauteuil_roulant`, `affectation_transport_personnes`, ... | Une ligne par version. La version « courante » a `date_fin_effet IS NULL`. |

Algorithme de calcul de la taxe avec historisation : voir `recherches.md` § 10.3.

**Justification** :

- Conforme à la lecture par défaut documentée dans `recherches.md` § 10.2 (caractéristiques effectives à chaque jour d'affectation).
- Cohérent avec la mention « les caractéristiques techniques, les conditions d'affectation, les périodes » de l'état récapitulatif annuel (CIBS art. L. 421-164).
- Permet à Floty de gérer correctement la conversion E85 (qui sera particulièrement importante à compter de 2025 avec l'apparition de l'abattement E85).
- Permet la traçabilité des modifications fiscales pour l'expert-comptable.
- Cohérent avec le mécanisme d'audit trail déjà prévu pour les attributions (cahier des charges § 4.5).
- Permet la régénération correcte des déclarations passées en cas de re-déploiement de seeder fiscal (cf. méthodologie § 10.3 — règle spécifique aux mises à jour fiscales propagées en production).

**Niveau de confiance** : **Haute** (choix produit cohérent avec la méthodologie et les usages fiscaux).

**À valider par expert-comptable** : Non sur le principe ; à mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :

- Refonte du modèle de données du véhicule pour séparer les caractéristiques administratives (immatriculation, marque, modèle, statut) des caractéristiques fiscales (qui sont historisées).
- Le moteur de calcul itère jour par jour et lit la version de caractéristiques fiscales effective à chaque jour. Coût de calcul augmenté (mais reste acceptable : 366 itérations × N véhicules × N entreprises).
- L'UI Floty affiche l'historique des caractéristiques fiscales sur la fiche véhicule (timeline chronologique).
- Le PDF récapitulatif mentionne, lorsqu'applicable, les modifications de caractéristiques en cours d'année (« Du 01/01 au 30/06 : essence Euro 6 ; du 01/07 au 31/12 : Superéthanol E85 Euro 6 — conversion homologuée le 01/07/2024 »).

**Mise à jour proposée du cahier des charges** : ajouter au § 2.1 une mention explicite sur la nécessité d'historiser les caractéristiques fiscales d'un véhicule. Cette mise à jour sera proposée à Renaud avec un commit dédié.

---

## Décision 7 — UX produit : fin d'attribution automatique à la date de sortie de flotte

**Contexte** : le cas G (véhicule mis hors-service en cours d'année — `recherches.md` § 9) ne pose pas de problème juridique (le décompte des jours d'affectation effective est explicite). Mais il pose un point UX : que faire des attributions ouvertes au moment de la sortie de flotte ?

**Options envisagées** :

- **Option A — Fin d'attribution manuelle uniquement** : l'utilisateur doit clôturer manuellement chaque attribution avant de saisir la sortie de flotte. Fastidieux et source d'erreurs.
- **Option B — Fin d'attribution automatique à la date de sortie de flotte** : Floty détecte les attributions ouvertes lors de la saisie de la sortie de flotte et propose de les clôturer automatiquement.
- **Option C — Pas de cohérence enforced** : Floty laisse l'utilisateur saisir des attributions sur un véhicule sorti de flotte. Inacceptable (calculs incohérents).

**Décision retenue** : **Option B — fin d'attribution automatique à la date de sortie de flotte**.

Mécanisme :

1. Lorsque l'utilisateur saisit un événement de sortie de flotte (vente, destruction, transfert) sur la fiche véhicule, Floty :
   a. Vérifie s'il existe des attributions ouvertes (date de fin postérieure à la date de sortie de flotte, ou attributions sans date de fin) sur ce véhicule.
   b. Si oui, affiche un modal de confirmation : « Ce véhicule est sorti de flotte le [date]. [N] attribution(s) en cours dépassent cette date. Souhaitez-vous les clôturer automatiquement à la date de sortie de flotte ? »
   c. Si l'utilisateur confirme, Floty clôture les attributions à la date de sortie de flotte.
2. Floty empêche la création de nouvelles attributions sur ce véhicule au-delà de la date de sortie de flotte (validation côté formulaire).

**Justification** :

- Conforme au principe Floty de minimisation de la saisie et de réduction des erreurs (cahier des charges § 1.2).
- Conforme à la cohérence de calcul (un véhicule sorti de flotte ne peut pas être attribué).
- Préserve l'audit trail (la clôture automatique est tracée comme une modification d'attribution avec mention « clôture automatique suite à sortie de flotte »).

**Niveau de confiance** : **Haute** (choix UX standard).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :

- Hook applicatif lors de la saisie d'un événement de sortie de flotte : vérification des attributions ouvertes, modal de confirmation, mise à jour automatique.
- Validation côté formulaire de création d'attribution : refus si la date dépasse la date de sortie de flotte du véhicule.
- Champ Floty `vehicule.date_sortie_flotte` (déjà prévu cahier des charges § 2.1, événement « date et motif de sortie de flotte »).
- Test unitaire : Cas G.1 et G.2 de `recherches.md` § 9.2.

---

## Décision 8 — Sources primaires retenues pour la traçabilité des cas particuliers 2024

**Contexte** : la méthodologie projet (§ 4.1) impose que toute règle fiscale soit tracée à des sources primaires identifiées. Il convient de désigner explicitement les sources primaires utilisées pour les cas particuliers 2024.

**Décision retenue** : trois sources primaires concordantes sont retenues, dans l'ordre d'autorité légale :

1. **Texte de loi — Code des Impositions sur les Biens et Services (CIBS)** :
   - **L. 421-2** (définition du véhicule de tourisme — frontière M1/N1) ;
   - **L. 421-119-1** (règle de bascule WLTP / NEDC / PA — trois cas) ;
   - **L. 421-134** (définition des trois catégories d'émissions de polluants) ;
   - **L. 421-164** (état récapitulatif annuel — historisation des caractéristiques) ;
   - **L. 421-129 et L. 421-141** (LCD — pour la consolidation Z-2024-002).
   Dans leurs versions applicables au 31 décembre 2023 (modifiées par la loi n° 2023-1322 du 29 décembre 2023, art. 97 — loi de finances pour 2024).

2. **Doctrine officielle — Bulletin Officiel des Finances Publiques (BOFiP-Impôts)** :
   - `BOI-AIS-MOB-10-30-20-20240710` (taxes d'affectation des véhicules de tourisme — version 2024) — particulièrement § 60 (frontière M1/N1), § 210-220 (bascule WLTP/NEDC/PA), § 230 (mécanique des barèmes), § 260-280 (catégories polluants) ;
   - `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes) — particulièrement § 150-190 (prorata, exemple fourrière) ;
   - `BOI-AIS-MOB-10-10` (définition transversale du véhicule de tourisme).

3. **Notices administratives DGFiP** :
   - Notice n° 2857-FC-NOT-SD (Cerfa 52374#03, édition décembre 2024) — partie II.1 (bascule WLTP/NEDC/PA, exception donnée CO₂ manquante) ;
   - Notice n° 2858-FC-NOT-SD (Cerfa 52375#03, édition décembre 2024) — partie I.2.a (champ d'application véhicules taxables).

**Justification** :

- Cette triangulation garantit qu'aucune règle d'implémentation ne repose sur une source unique.
- Les conditions techniques de la frontière M1/N1, de la bascule WLTP/NEDC/PA et de la classification polluants sont vérifiées sur trois sources primaires concordantes.
- Les sources tertiaires (S10 PwC, S11 FNA, S12 Drive to Business, S13 Compta-Online, S14 Legifiscal) ont été consultées pour confirmer la lecture sans être autoritaires en cas de divergence.

**Niveau de confiance** : **Haute** (triangulation primaire complète sur tous les cas particuliers résolus).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :

- Les références S1 à S8 de `sources.md` sont les sources de vérité pour les règles d'implémentation des cas particuliers 2024 dans le moteur de calcul Floty.
- Toute mise à jour des règles (notamment les bascules au 01/01/2025 — suppression de l'option forfaitaire trimestrielle, suppression de l'exonération hybride § L. 421-125) sera tracée dans le sous-dossier `2025/cas-particuliers/` avec sa propre triangulation primaire.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Décisions initiales cas particuliers 2024 — 8 décisions documentées : (1) algorithme de qualification du « type fiscal » (frontière M1/N1) ; (2) algorithme de détermination du barème pour les véhicules importés d'occasion ; (3) bascule automatique sur PA en cas de donnée CO₂ manquante (point UX) ; (4) algorithme de classement étendu pour les hybrides Diesel-électrique (catégorie polluants) ; (5) consolidation des incertitudes Z-2024-001 et Z-2024-002 sans résolution ; (6) exigence produit historisation des caractéristiques fiscales ; (7) UX fin d'attribution automatique à la date de sortie de flotte ; (8) sources primaires retenues. Niveau de confiance Haute pour 6 décisions, Moyenne pour 2 (Décision 4 — hybride Diesel sans source primaire explicite ; Décision 5 — incertitudes maintenues ouvertes). |

# Décisions — Taxe annuelle sur les émissions de CO₂ — Exercice 2024

> **Statut** : Version 0.1
> **Auteur** : Micha MEGRET (prestataire)
> **Date** : 22 avril 2026
> **Renvoi sources** : voir `sources.md` (cotes S1, S2, …) et `recherches.md` pour les analyses détaillées.

---

## Décision 1 — Méthode applicable à un véhicule donné (WLTP, NEDC ou PA)

**Contexte** : Trois barèmes coexistent en 2024 (WLTP, NEDC, puissance administrative). Avant tout calcul, il faut déterminer sans ambiguïté lequel s'applique à un véhicule donné. Les sources primaires (S1 partie II.1, S2 §210-220) fixent trois conditions hiérarchiques.

**Options envisagées** :
- **Option A — Trois critères en cascade** : appliquer la règle officielle telle que rédigée dans la notice S1 :
  1. WLTP si véhicule immatriculé en France pour la première fois à compter du 01/03/2020 ET émissions CO₂ déterminées par WLTP ;
  2. NEDC si véhicule a fait l'objet d'une réception européenne, immatriculé pour la première fois à compter du 01/06/2004 ET n'était pas affecté à des fins économiques par l'entreprise affectataire avant le 01/01/2006 ;
  3. PA pour tous les autres cas, ou si CO₂ non déterminé.
- **Option B — Simplification par date France seule** : appliquer WLTP si 1ère immat. France ≥ 01/03/2020, NEDC si entre 01/06/2004 et 28/02/2020, PA sinon. Plus simple mais ignore la condition « affecté à l'entreprise avant le 01/01/2006 » qui peut basculer un véhicule en PA bien qu'immatriculé après 2004.
- **Option C — Saisie manuelle obligatoire** : laisser l'utilisateur indiquer la méthode, sans tentative automatique.

**Décision retenue** : **Option A — règle officielle stricte en cascade**.

L'algorithme applicatif Floty est :

```
SI (date_premiere_immat_France ≥ 2020-03-01) ET (méthode_homologation == WLTP) ET (co2_wltp renseigné) :
    barème = WLTP
SINON SI (date_premiere_immat_France ≥ 2004-06-01)
       ET (date_premiere_affectation_par_entreprise ≥ 2006-01-01)
       ET (réception_européenne == true)
       ET (co2_nedc renseigné) :
    barème = NEDC
SINON :
    barème = Puissance Administrative
    (et il faut que la puissance_administrative soit renseignée)
```

**Justification** : c'est la règle officielle reproduite à l'identique de la notice DGFiP (S1 partie II.1) et confirmée par BOFiP (S2 §210-220). Toute simplification créerait des erreurs de qualification dans des cas réels (notamment : véhicules WLTP sans CO₂ mesuré qui doivent basculer sur PA ; véhicules pré-2006 affectés depuis longtemps qui restent sur PA même si immatriculés après 2004).

**Niveau de confiance** : **Haute**. Règle directement issue de la notice officielle DGFiP, croisée avec BOFiP.

**À valider par expert-comptable** : Non (règle textuelle directe).

**Conséquences sur l'implémentation** :
- La fiche véhicule Floty doit collecter quatre champs déterminants : `date_premiere_immat_France`, `methode_homologation` (WLTP / NEDC / aucune), `date_premiere_affectation_par_entreprise` (date à laquelle l'entreprise actuelle ou une entreprise précédente du groupe a commencé à affecter le véhicule à des fins économiques), `co2_wltp`, `co2_nedc`, `puissance_administrative`.
- Le moteur de calcul détermine automatiquement le barème applicable et le rend visible (« Barème appliqué : WLTP » dans la fiche déclaration), avec justification (« Date 1ère immat. France 15/06/2022 ≥ 01/03/2020 et méthode WLTP renseignée → WLTP »).
- En cas de données insuffisantes (ex: WLTP attendu mais CO₂ vide), le système doit basculer sur PA et signaler explicitement l'auto-bascule à l'utilisateur (« Donnée CO₂ WLTP manquante — bascule automatique sur barème puissance administrative »).

---

## Décision 2 — Méthode d'arrondi et niveau d'application

**Contexte** : La règle officielle (S1 p. 6, S3 §150, CIBS L. 131-1) stipule un arrondi à l'euro le plus proche **du montant total à payer**. Mais Floty produit des calculs intermédiaires (par véhicule, par entreprise, par tranche). À quel(s) niveau(x) appliquer l'arrondi ? Et quelle règle (« half-up » ? autre ?).

**Options envisagées** :
- **Option A — Arrondi au seul total final par redevable** : conserver la précision décimale tout au long des calculs (par tranche, par véhicule, après prorata, sommation par entreprise) et n'arrondir qu'au total ultime déclaré par chaque entreprise. Conforme strict à la lettre du texte fiscal.
- **Option B — Arrondi par véhicule puis somme** : arrondir à l'euro après calcul prorata pour chaque véhicule, puis sommer. Plus lisible dans le PDF (chaque ligne véhicule a un montant entier en €).
- **Option C — Arrondi à chaque étape** : arrondir tranche par tranche, puis ligne par ligne, puis total. Provoque des écarts cumulés.

**Décision retenue** : **Option A — Arrondi au seul total final par redevable**, avec arrondi « half-up commercial » (≥ 0,50 → 1 ; < 0,50 → 0).

**Justification** :
- La notice S1 dit textuellement : « Le montant total de la taxe à payer est arrondi à l'euro le plus proche. » — c'est le **total**, pas chaque ligne.
- Le BOFiP S3 §150 confirme : arrondi au niveau du montant final par véhicule/redevable.
- Le cahier des charges Floty §7.3 dit : « Les arrondis se font uniquement en fin de calcul (pas d'arrondis intermédiaires). » — concorde.
- L'option B (arrondi par véhicule) génère un écart d'arrondi qui peut atteindre 0,50 € × N véhicules en valeur absolue. Pour une flotte de 100 véhicules par entreprise, écart théorique max 50 € — non négligeable. Évité par l'option A.

**Présentation PDF** : dans le tableau détaillé du PDF récapitulatif, on peut afficher pour chaque véhicule un montant à 2 décimales (par exemple « 144,64 € ») ou arrondi à l'euro pour la lisibilité. **Choix Floty** : afficher 2 décimales par ligne véhicule (transparence du calcul) ; ne pas arrondir avant la somme finale ; afficher la somme finale arrondie à l'euro avec mention « Total à déclarer (arrondi à l'euro) : X € ».

**Niveau de confiance** : **Haute**. Règle textuelle officielle.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Les colonnes « Tarif annuel CO₂ », « Taxe CO₂ après prorata », « Taxe polluants », « Total par véhicule » du PDF (cf. cahier des charges §5.7) doivent être présentées avec **2 décimales** chacune.
- La ligne « Total à déclarer » est seule arrondie à l'euro.
- En base de données, conserver les montants en `decimal(10, 2)` (centimes) ou plus précis. Le calcul du tarif annuel via barèmes WLTP/NEDC donne toujours un entier (les fractions × tarifs marginaux sont entiers), seule la multiplication par le prorata jours/365 ou jours/366 introduit des décimales.

---

## Décision 3 — Traitement de l'année 2024 comme année bissextile (366 jours)

**Contexte** : 2024 est une année bissextile (366 jours, du 29 février inclus). Le calcul du prorata utilise au dénominateur « le nombre de jours de l'année civile » (S1 partie II.2.a, S3 §160). Cette formulation laisse-t-elle place à interprétation ?

**Options envisagées** :
- **Option A — Dénominateur = 366 pour 2024** (et 365 pour les années non bissextiles) : application stricte de « nombre de jours de l'année civile ».
- **Option B — Dénominateur = 365 systématique** : simplification, mais incompatible avec le texte.

**Décision retenue** : **Option A — 366 jours pour 2024, conformément à la lettre du texte et à l'exemple officiel BOFiP.**

**Justification** :
- L'exemple 2 du BOFiP S2 §230 utilise **explicitement 366** pour 2024 : « 173 × 306 / 366 = 144,64 € ». C'est dispositif.
- La notice S1 et le BOFiP S3 §160 disent « nombre total de jours de l'année civile », sans dérogation.
- Le cahier des charges Floty §2.4 dit explicitement : « Les années bissextiles (366 jours) doivent être correctement gérées dans les calculs de prorata. » — concorde.

**Niveau de confiance** : **Haute**. Confirmé par exemple officiel BOFiP utilisant 366.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty doit lire dynamiquement le nombre de jours de l'année concernée (`365` pour 2025, 2026, 2027 ; `366` pour 2024 — et plus généralement pour toute année divisible par 4 mais pas par 100, sauf si divisible par 400, soit 2024, 2028, 2032, 2052, …).
- La fiche déclaration doit afficher le dénominateur utilisé (« Sur 366 jours en 2024 »).
- Tests unitaires impératifs : véhicule affecté toute l'année 2024 → prorata = 366/366 = 100 %, taxe = tarif annuel plein.

---

## Décision 4 — Formule mathématique du barème WLTP/NEDC à tarif marginal

**Contexte** : Les barèmes WLTP et NEDC fonctionnent par tranches à tarif marginal. Plusieurs notations sont possibles pour exprimer la fraction des émissions tombant dans chaque tranche. Il faut figer une formule mathématique non ambiguë utilisée par le moteur Floty.

**Options envisagées** :
- **Option A — Bornes inférieures inclusives** : tranche `[a, b]` avec `a` et `b` entiers ; fraction prise = `max(0, min(co2, b) − a + 1)` si on considère que la tranche couvre `b − a + 1` valeurs entières.
- **Option B — Bornes par seuils** : la tranche `(s_inf, s_sup]` couvre `s_sup − s_inf` grammes ; fraction = `max(0, min(co2, s_sup) − s_inf)`. C'est la notation utilisée par le BOFiP : `(55−14) × 1` pour la tranche « 15 à 55 ».

**Décision retenue** : **Option B — bornes par seuils, formule `fraction_dans_tranche = max(0, min(co2, s_sup) − s_inf)` avec `s_sup` = borne supérieure de la tranche et `s_inf` = borne inférieure de la tranche précédente (ou 0 pour la première tranche)**.

Pour le barème WLTP 2024, on définit :

| # | Tranche notice | s_inf (exclusive) | s_sup (inclusive) | Tarif (€/g) |
|---|---|---|---|---|
| 1 | Jusqu'à 14 | 0 | 14 | 0 |
| 2 | De 15 à 55 | 14 | 55 | 1 |
| 3 | De 56 à 63 | 55 | 63 | 2 |
| 4 | De 64 à 95 | 63 | 95 | 3 |
| 5 | De 96 à 115 | 95 | 115 | 4 |
| 6 | De 116 à 135 | 115 | 135 | 10 |
| 7 | De 136 à 155 | 135 | 155 | 50 |
| 8 | De 156 à 175 | 155 | 175 | 60 |
| 9 | À partir de 176 | 175 | +∞ | 65 |

Et le tarif annuel plein s'obtient par :

```
tarif_annuel_plein = somme sur toutes les tranches de :
    max(0, min(co2, s_sup_tranche) − s_inf_tranche) × tarif_marginal_tranche
```

**Justification** : cette notation reproduit exactement le calcul de l'exemple BOFiP `14×0 + (55−14)×1 + (63−55)×2 + (95−63)×3 + (100−95)×4 = 173 €`. Elle est mathématiquement non ambiguë et facile à coder. Le résultat pour un véhicule à 100 g/km est bien 173 €.

L'équivalent pour le barème NEDC 2024 utilise les seuils `(0, 12, 45, 52, 79, 95, 112, 128, 145, +∞)` et les tarifs `(0, 1, 2, 3, 4, 10, 50, 60, 65)`.

**Vérification croisée — exemple WLTP construit** : véhicule à 130 g CO₂/km en 2024 :
- Tranche 1 (0;14], 0 €/g : 14 × 0 = 0
- Tranche 2 (14;55], 1 €/g : 41 × 1 = 41
- Tranche 3 (55;63], 2 €/g : 8 × 2 = 16
- Tranche 4 (63;95], 3 €/g : 32 × 3 = 96
- Tranche 5 (95;115], 4 €/g : 20 × 4 = 80
- Tranche 6 (115;135], 10 €/g : (130−115) × 10 = 15 × 10 = 150
- Total = 0 + 41 + 16 + 96 + 80 + 150 = **383 €**

(Un véhicule à 130 g/km 2024 coûte donc 383 € en tarif annuel plein — utilisable pour test unitaire Floty.)

**Niveau de confiance** : **Haute**. Formule directement vérifiée avec exemple BOFiP officiel (100 g/km → 173 € ✓).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Stockage en base de données du barème sous forme d'une table `tax_co2_wltp_brackets_2024` (par exemple) avec colonnes `bracket_index`, `s_inf` (decimal nullable, NULL = 0), `s_sup` (decimal nullable, NULL = +∞), `marginal_rate_eur_per_gram`.
- Le moteur de calcul itère sur les tranches dans l'ordre, calcule la fraction et somme.
- Tests unitaires obligatoires : 0, 14, 15, 55, 56, 100 (= 173 €), 130 (= 383 €), 175, 176, 200 g/km (vérifier la robustesse aux bornes).

---

## Décision 5 — Choix systématique du calcul prorata journalier (et non option trimestrielle)

**Contexte** : Le redevable a, en 2024, le choix entre un prorata journalier (par défaut) et une option forfaitaire trimestrielle (S1 partie II.2.b). Le cahier des charges Floty (§5.4) ne mentionne que le calcul journalier. À acter formellement que Floty ne propose **pas** l'option trimestrielle.

**Options envisagées** :
- **Option A — Calcul journalier exclusif** : Floty calcule toujours par jours, conformément à la règle de principe et au cahier des charges.
- **Option B — Proposer un toggle « option trimestrielle »** : permettre au redevable de choisir l'option dans Floty, qui appliquerait alors le prorata par trimestres entiers d'affectation × 25 %.

**Décision retenue** : **Option A — Calcul journalier exclusif**.

**Justification** :
- Le cahier des charges (§5.4) prescrit explicitement le prorata journalier ; Floty est conçu pour saisir des attributions journalières (cahier des charges §2.4 : « granularité = le jour »).
- Le calcul journalier est l'**option par défaut** légalement (S1 partie II.2.a) et le choix le plus précis.
- L'option trimestrielle est **supprimée à compter du 1er janvier 2025** (S2 §300-360) — elle ne survivra qu'un an. Implémenter un toggle pour 2024 uniquement créerait de la dette technique pour aucun gain.
- L'option trimestrielle est globale (s'applique à **tous les véhicules** du redevable, pas par véhicule), donc même un usage hybride serait impossible.
- Aucun client identifié n'aurait un avantage à utiliser l'option trimestrielle dans le contexte Floty.

**Niveau de confiance** : **Haute**.

**À valider par expert-comptable** : Non, mais à mentionner dans le rapport de livraison pour transparence.

**Conséquences sur l'implémentation** :
- Pas de UI pour activer une option trimestrielle.
- Documentation interne (et FAQ utilisateur) : « Floty calcule la taxe au jour près. L'option forfaitaire trimestrielle (CIBS art. L. 421-107) n'est pas implémentée — d'autant qu'elle est supprimée à compter du 1er janvier 2025. »

---

## Décision 6 — Mécanique de calcul du barème puissance administrative (PA)

**Contexte** : Le barème PA, défini par CIBS art. L. 421-122, doit être appliqué de manière non équivoque. Le texte de l'article énonce « un tarif marginal à chaque fraction de la puissance administrative », formulation strictement identique à celle des articles L. 421-120 (WLTP) et L. 421-121 (NEDC). La doctrine BOFiP confirme par ailleurs que **les trois barèmes** (WLTP, NEDC, PA) sont des « barèmes progressifs par tranches » (BOI-AIS-MOB-10-30-20 § 230). Le cahier des charges Floty (§ 5.2, note finale) en donne la même lecture : « le barème puissance administrative fonctionne aussi par fractions et tarif marginal, exactement comme le barème WLTP mais sur les CV au lieu des g/km ».

**Options envisagées** :
- **Option A — Tarif marginal × fraction (cohérence avec WLTP/NEDC, conforme à L. 421-122 et au BOFiP § 230)** : pour chaque tranche traversée, on multiplie le nombre de CV inclus dans la tranche par le tarif marginal de cette tranche, puis on somme. Exemple 10 CV : 3 × 1 500 + 3 × 2 250 + 4 × 3 750 = 26 250 €.
- **Option B — Forfait par tranche atteinte (additif simple)** : pour chaque tranche dont la borne inférieure est atteinte, on ajoute son tarif. Exemple 10 CV : 1 500 + 2 250 + 3 750 = 7 500 €. Cette mécanique n'est pas celle décrite par CIBS art. L. 421-122 (qui parle de « tarif marginal à chaque fraction ») et serait incohérente avec le BOFiP § 230 qui range PA, WLTP et NEDC dans la même catégorie de « barèmes progressifs par tranches ».

**Décision retenue** : **Option A — tarif marginal × fraction**, identique en mécanique à WLTP et NEDC.

**Justification** :
- Texte de l'article L. 421-122 CIBS, dans sa version applicable au 31/12/2023 : « Le barème en puissance administrative associant un tarif marginal à chaque fraction de la puissance administrative, exprimée en chevaux administratifs ». La formulation est identique mot pour mot à celle de L. 421-120 (WLTP) et L. 421-121 (NEDC) — la mécanique est donc nécessairement la même.
- BOFiP BOI-AIS-MOB-10-30-20 § 230 : « Les trois barèmes, CO2-WLTP, CO2-NEDC et PA sont repris respectivement à l'article L. 421-120 du CIBS, à l'article L. 421-121 du CIBS et à l'article L. 421-122 du CIBS. […] Les trois barèmes sont des barèmes progressifs par tranches ».
- L'exemple chiffré officiel BOFiP § 230 pour le barème WLTP (100 g/km en 2024 = 14×0 + (55−14)×1 + (63−55)×2 + (95−63)×3 + (100−95)×4 = 173 €) confirme la mécanique « (borne haute atteinte − borne haute de la tranche précédente) × tarif marginal », sommée sur les tranches traversées. Cette mécanique est applicable à l'identique au barème PA en remplaçant « g/km » par « CV ».
- Le cahier des charges Floty (§ 5.2, note finale) énonce déjà cette mécanique en toutes lettres.

**Niveau de confiance** : **Haute** (convergence de trois sources primaires : texte de l'article, doctrine BOFiP, cahier des charges).

**À valider par expert-comptable** : Non. La triangulation est complète et univoque.

**Conséquences sur l'implémentation** :
- Le barème PA stocké en base est structuré comme les barèmes WLTP et NEDC : une suite de tranches `[borne_inf, borne_sup, tarif_marginal_par_CV]`.
- Algorithme Floty pour le calcul du tarif annuel plein PA :
  ```
  tarif_annuel_pa = 0
  borne_basse_precedente = 0
  pour chaque tranche [borne_inf, borne_sup, tarif_marginal] dans l'ordre :
      si puissance_admin >= borne_inf :
          fraction_dans_tranche = min(puissance_admin, borne_sup) - borne_basse_precedente
          tarif_annuel_pa += fraction_dans_tranche * tarif_marginal
          borne_basse_precedente = borne_sup
  ```
- Tests unitaires PA 2024 (à reproduire en TU) :
  - 1 CV → 1 × 1 500 = **1 500 €**
  - 3 CV → 3 × 1 500 = **4 500 €**
  - 4 CV → 3 × 1 500 + 1 × 2 250 = **6 750 €**
  - 6 CV → 3 × 1 500 + 3 × 2 250 = **11 250 €**
  - 7 CV → 3 × 1 500 + 3 × 2 250 + 1 × 3 750 = **15 000 €**
  - 10 CV → 3 × 1 500 + 3 × 2 250 + 4 × 3 750 = **26 250 €**
  - 11 CV → 4 500 + 6 750 + 15 000 + 1 × 4 750 = **31 000 €**
  - 15 CV → 4 500 + 6 750 + 15 000 + 5 × 4 750 = **50 000 €**
  - 16 CV → 4 500 + 6 750 + 15 000 + 5 × 4 750 + 1 × 6 000 = **56 000 €**

**Note sur le champ d'application** : le barème PA n'est applicable que dans deux situations résiduelles précisées par CIBS art. L. 421-119-1, 3° et BOFiP § 220 : véhicules sans réception européenne, ou véhicules déjà immatriculés et affectés à une activité économique par l'entreprise affectataire avant le 1er janvier 2006. Cela en fait un cas marginal pour le périmètre Floty, ce qui explique la cohérence du barème (élevé) avec son objectif dissuasif vis-à-vis des véhicules anciens fortement motorisés.

---

## Décision 7 — Affichage dans le PDF récapitulatif des éléments justifiant le barème appliqué

**Contexte** : Le PDF récapitulatif (cahier des charges §5.7) doit servir de pièce justificative. Pour qu'un expert-comptable puisse auditer le calcul, il doit pouvoir vérifier que le bon barème a été appliqué à chaque véhicule, et avec quels paramètres (date 1ère immat., méthode WLTP/NEDC/PA, valeur CO₂ ou CV, formule appliquée, prorata).

**Options envisagées** :
- **Option A — PDF minimal** : afficher juste les colonnes du cahier des charges (immat., modèle, jours, prorata, montant) sans détailler les calculs intermédiaires.
- **Option B — PDF étoffé** : ajouter pour chaque véhicule la justification du barème (« WLTP appliqué car 1ère immat. France 15/06/2022 ≥ 01/03/2020 »), le détail tranche par tranche du tarif annuel plein, et la formule (tarif × jours / 366).
- **Option C — PDF étoffé avec annexe complète** : option B + une annexe par véhicule détaillant la formule complète.

**Décision retenue** : **Option B — PDF étoffé**, intégrant pour chaque véhicule :
- les caractéristiques fiscales (déjà prévu cahier des charges §5.7) : type de carburant, émissions CO₂, norme Euro, puissance fiscale, méthode de calcul (WLTP/NEDC/PA)
- la **justification de la méthode appliquée** (« WLTP : 1ère immat. France ≥ 01/03/2020 »)
- le **tarif annuel plein** avec mention de la formule de calcul (« 173 € = 14×0 + 41×1 + 8×2 + 32×3 + 5×4 » pour un cas WLTP 100 g/km)
- la **formule de prorata** appliquée (« 173 × 306/366 »)
- le **montant à 2 décimales** (« 144,64 € »)

**Justification** :
- L'**état récapitulatif annuel** prévu par CIBS art. L. 421-164 (S3 §400-410) doit mentionner « les caractéristiques techniques, les conditions d'affectation, les périodes, les exonérations applicables ». Floty produit ce document ; mieux vaut être exhaustif.
- L'expert-comptable validateur doit pouvoir vérifier ligne par ligne sans avoir à recalculer mentalement.
- Le surcoût de présentation (quelques lignes par véhicule) est négligeable face au gain en auditabilité.

**Niveau de confiance** : **Haute** (choix produit, sans risque fiscal).

**À valider par expert-comptable** : Non (choix de présentation, pas de calcul).

**Conséquences sur l'implémentation** :
- La structure de données interne du résultat de calcul doit conserver les éléments intermédiaires (tranches, fractions, contributions par tranche) jusqu'à l'export PDF.
- Modèle de génération PDF prévu pour intégrer ces colonnes.

---

## Décision 8 — Définition du « jour d'affectation » comptabilisé dans le numérateur du prorata

**Contexte** : Floty enregistre des **attributions journalières** d'un véhicule à une entreprise (cahier des charges §2.4). Le numérateur du prorata fiscal (S1 partie II.2.a) est « la durée annuelle pendant laquelle l'entreprise redevable a été affectataire du véhicule à des fins économiques, exprimée en nombre de jours ». Mais comment compte-t-on un jour d'attribution Floty au sens fiscal ? Tous les jours d'attribution comptent-ils ? Comment traiter les indisponibilités ?

**Options envisagées** :
- **Option A — Tout jour Floty d'attribution = 1 jour fiscal** : approche directe ; chaque ligne `attribution(date, véhicule, entreprise)` compte 1 jour pour l'entreprise concernée. Les indisponibilités (maintenance, sinistre) **ne réduisent pas** le numérateur — c'est l'entreprise qui supporte économiquement le véhicule sur la période même immobilisé pour réparation.
- **Option B — Indisponibilités à exclure** : retrancher du numérateur les jours d'indisponibilité.
- **Option C — Distinguer selon le type d'indisponibilité** : exclure uniquement les indisponibilités résultant d'une décision **publique** (fourrière, immobilisation administrative), conformément au BOFiP S3 §190 ; conserver les autres (maintenance, sinistre) qui restent du temps d'« affectation ».

**Décision retenue** : **Option C — distinction par type d'indisponibilité**.

Précisément :
- Indisponibilités de type **« Fourrière / immobilisation administrative »** (au sens du décret pouvoirs publics) : **réduisent** le numérateur. Le véhicule n'est plus considéré comme affecté pendant cette période.
- Indisponibilités de type **« Maintenance »**, **« Contrôle technique »**, **« Sinistre »** (privés), **« Autre »** : **ne réduisent pas** le numérateur. L'entreprise reste affectataire du véhicule, juste momentanément indisponible.

**Justification** :
- Le BOFiP S3 §190 mentionne **explicitement** la mise en fourrière comme cause de réduction (« 15 jours en fourrière = 350 / 365 = 95,9 % »). C'est la seule cause d'extinction temporaire de l'affectation citée dans le BOFiP.
- A contrario, une panne mécanique ou un sinistre n'« éteint » pas l'affectation économique : le véhicule reste détenu/loué par l'entreprise et lui sera à nouveau utilisable après réparation. Il continue de relever de l'affectation au sens fiscal.
- Cette distinction est cohérente avec le cahier des charges Floty (§2.5) qui distingue déjà « Maintenance », « CT », « Sinistre », « Fourrière / immobilisation administrative », « Autre ». Il suffit de marquer le type « Fourrière / immobilisation administrative » comme **fiscalement déductif**.

**Niveau de confiance** : **Moyenne**. Le BOFiP cite uniquement la fourrière publique. Pour les indisponibilités longues (sinistre prolongé immobilisant le véhicule plusieurs mois), il existe une zone d'incertitude — peut-on encore parler d'affectation économique ? La règle prudente (= incluant ces jours dans le numérateur) majore la taxe et est donc conforme au principe de prudence (méthodologie §8.3). Mais une lecture inverse (l'indisponibilité longue n'est plus de l'affectation) est défendable.

**À valider par expert-comptable** : Oui. Point à inscrire dans `incertitudes.md`.

**Conséquences sur l'implémentation** :
- Le moteur de calcul Floty utilise comme numérateur le compte des `attribution(date, véhicule, entreprise)` non couvertes par une `indisponibilite(date, véhicule, type='Fourrière / immobilisation administrative')`.
- Note UX : l'utilisateur saisissant une indisponibilité doit voir un indicateur sur les types ayant un impact fiscal (« Cette indisponibilité réduira le prorata fiscal de l'entreprise affectataire »).
- Cas litigieux : si un véhicule est attribué le jour J à l'entreprise A, et qu'une indisponibilité fourrière est saisie sur ce même jour J — le jour est-il compté pour A ou pas ? **Décision** : non, l'indisponibilité fourrière prime sur l'attribution (le véhicule n'est physiquement pas utilisable, donc pas affecté). Cette règle évite le double-compte.

---

## Décision 9 — Bornes calendaires de l'année fiscale = année civile stricte

**Contexte** : La taxe est annuelle (S1, S3). Floty doit caler la « période de taxation 2024 » sur l'année civile.

**Options envisagées** :
- **Option A — Année civile stricte (01/01/2024 → 31/12/2024)**.
- **Option B — Exercice comptable de l'entreprise** (potentiellement décalé).

**Décision retenue** : **Option A — Année civile stricte**.

**Justification** :
- Le BOFiP S3 §160 et la notice S1 utilisent l'expression « année civile ».
- Indépendance vis-à-vis de l'exercice comptable des entreprises utilisatrices (qui peut varier).
- Cohérent avec le cahier des charges §2.4 (« L'année de référence est l'année civile »).

**Niveau de confiance** : **Haute**.

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Toute requête de calcul fiscal pour 2024 délimite la fenêtre temporelle aux dates `[2024-01-01, 2024-12-31]` strictes.
- Si une attribution Floty chevauche les frontières d'année (ex : du 20/12/2023 au 10/01/2024), elle est **scindée fictivement** au calcul : 12 jours en 2023 (relèvent du barème 2023, instruction séparée), 10 jours en 2024 (relèvent du barème 2024 instruit ici).

---

## Décision 10 — Sources primaires retenues pour la traçabilité des barèmes

**Contexte** : La méthodologie projet (§ 4.1) impose que toute valeur numérique (tarif, seuil, borne de tranche) soit tracée à une source primaire. Il convient donc de désigner explicitement les sources primaires utilisées pour la taxe CO₂ 2024.

**Décision retenue** : Trois sources primaires concordantes sont retenues, dans l'ordre d'autorité légale :

1. **Texte de loi — Code des impositions sur les biens et services (CIBS), articles L. 421-119 à L. 421-122**, dans leur version applicable au 31 décembre 2023 (modifiée par la loi n° 2023-1322 du 29 décembre 2023 — loi de finances pour 2024). Source d'autorité légale, fait foi devant l'administration fiscale.
2. **Doctrine officielle — Bulletin Officiel des Finances Publiques (BOFiP-Impôts)**, identifiants `BOI-AIS-MOB-10-30-20-20240710` (taxes d'affectation des véhicules de tourisme — section principale) et `BOI-AIS-MOB-10-30-10-20250528` (dispositions communes aux taxes d'affectation). Doctrine fiscale opposable à l'administration.
3. **Notice administrative — Notice DGFiP n° 2857-FC-NOT-SD (Cerfa 52374#03)** dans sa version applicable à la déclaration des taxes 2024. Reproduit textuellement les barèmes et donne des exemples chiffrés.

**Justification** :
- Cette triangulation garantit qu'aucune valeur numérique ne repose sur une source unique.
- L'exemple chiffré officiel BOFiP § 230 (WLTP 100 g/km en 2024 = 173 €) a été reproduit avec succès en appliquant la formule de calcul issue de l'article L. 421-120, ce qui valide la mécanique de calcul à la fois pour le WLTP et — par identité de structure des articles L. 421-120, L. 421-121 et L. 421-122 — pour les barèmes NEDC et PA.
- Les sources tertiaires (PwC Avocats, FNA, Legifiscal, Drive to Business, Compta-Online) ont été consultées pour valider l'interprétation pratique mais ne sont pas autoritaires en cas de divergence.

**Niveau de confiance** : **Haute** (triangulation primaire complète, exemple officiel reproduit).

**À valider par expert-comptable** : Non.

**Conséquences sur l'implémentation** :
- Les références S1 (notice DGFiP), S2/S3 (BOFiP) et S12-S15 (articles CIBS L. 421-119 à L. 421-122) de `sources.md` sont les sources de vérité pour les valeurs numériques implémentées dans le moteur de calcul.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 22/04/2026 | Micha MEGRET | Décisions initiales taxe CO₂ 2024 — 10 décisions documentées (méthode, arrondi, bissextile, formule WLTP/NEDC, calcul journalier, mécanique PA, présentation PDF, jours d'affectation, année civile, sources primaires). |
| 0.2 | 23/04/2026 | Micha MEGRET | Vérification croisée des articles CIBS L. 421-119 à L. 421-122 directement sur Légifrance (texte intégral applicable au 31/12/2023). Décision 6 réécrite : la mécanique du barème PA est confirmée identique à WLTP/NEDC (tarif marginal × fraction), conformément au texte de l'article L. 421-122 et à la doctrine BOFiP § 230. Tests unitaires PA recalculés. Décision 10 réécrite pour formaliser les trois sources primaires retenues (CIBS, BOFiP, notice DGFiP). |

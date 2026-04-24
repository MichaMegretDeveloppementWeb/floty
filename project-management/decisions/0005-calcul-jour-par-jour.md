# ADR-0005 — Calcul fiscal jour-par-jour (granularité strictement journalière)

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Décidée initialement** : 20 avril 2026 (brainstorming de cadrage)
> **Formalisée** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Le modèle Floty repose sur une **attribution de véhicules à des entreprises utilisatrices**, avec un prorata fiscal calculé sur la durée d'utilisation. Une question structurante devait être tranchée dès le cadrage : **quelle est la granularité temporelle de l'attribution et du calcul de prorata ?**

Les granularités envisageables :

- **Horaire** : une attribution peut démarrer et se terminer à une heure précise.
- **Journalière** : une attribution couvre un ou plusieurs jours civils entiers.
- **Hebdomadaire** : une attribution couvre une ou plusieurs semaines.
- **Mensuelle** : une attribution couvre un ou plusieurs mois.

Cette décision conditionne tout le reste : modèle de données, interface de saisie, algorithme de prorata, formule fiscale, cohérence avec le texte CIBS.

## Décision

**La granularité d'attribution et de calcul est strictement le jour civil.**

Concrètement :

- Une attribution couvre **un ou plusieurs jours entiers** (lundi-dimanche, 7 jours sur 7).
- Aucune granularité inférieure (heure, demi-journée, partie de journée) n'est modélisée.
- Le **prorata fiscal** est calculé comme : `nombre de jours d'attribution × tarif annuel / nombre de jours dans l'année (365 ou 366)`.
- Pour les années bissextiles (2024, 2028…), le dénominateur est **366**. La reconnaissance de l'année bissextile est automatique.
- L'interface de saisie (vue planning globale, vue par entreprise, vue par véhicule, saisie hebdomadaire) expose exclusivement des cellules-jours.

## Justification

### Conformité au texte CIBS

Le Code des Impositions sur les Biens et Services (CIBS) articles L. 421-93 et suivants formulent le prorata fiscal en **jours de détention** ou **jours d'affectation**. Le BOFiP `BOI-AIS-MOB-10-30-10` § 150-190 détaille le calcul sur une base journalière. L'exemple officiel BOFiP § 230 exemple 2 pour 2024 utilise explicitement `173 × 306/366 = 144,64 €`. Adopter la granularité journalière dans Floty s'aligne strictement sur la mécanique fiscale.

### Aucune exigence métier d'infra-journalier

Les contrats de mise à disposition de véhicules entre la société de location et ses entreprises utilisatrices (cœur du modèle Renaud) se comptent en jours, pas en heures. Aucun usage métier ne nécessite une granularité plus fine.

### Cohérence avec la contrainte d'unicité

La règle « un véhicule ne peut être attribué qu'à une seule entreprise par jour » (cahier des charges § 2.4) est naturelle en granularité journalière. En granularité infra-journalière, il faudrait définir des règles de partage qui n'ont aucun sens fiscal ni pratique.

### Simplicité d'implémentation et d'ergonomie

Une heatmap annuelle de 100 véhicules × 366 jours = 36 600 cellules reste performante. Une heatmap plus granulaire serait ingérable en affichage et en saisie. La saisie hebdomadaire « mode tableur » prévue au cahier des charges § 3.6 repose sur l'hypothèse d'une grille 7 colonnes (lun-dim) × N véhicules — granularité journalière.

### Robustesse des calculs

Avec la granularité journalière, les calculs sont **déterministes et stables** : deux attributions couvrant exactement les mêmes jours produisent exactement la même taxe. Aucune question de seuillage (« une attribution de 14h à 18h compte-t-elle pour une journée ou non ? ») ne se pose.

### Compatibilité avec la mécanique LCD (R-2024-021)

Le seuil d'exonération LCD est de **30 jours consécutifs** (cumul annuel par couple). Ce seuil est exprimé en jours dans la loi. La granularité journalière rend son évaluation triviale.

## Alternatives écartées

### Alternative 1 — Granularité horaire ou infra-journalière

Modéliser l'attribution avec une heure de début et une heure de fin.

**Rejetée** pour les raisons suivantes :

- Aucun usage métier identifié qui justifierait cette finesse.
- Complexité de saisie et d'affichage sans contrepartie fonctionnelle.
- Incompatibilité avec la formulation du CIBS (qui raisonne en jours).
- Multiplication des cas limites (calculs de quarts de journée, arrondis d'heures, gestion des fuseaux horaires) sans bénéfice.

### Alternative 2 — Granularité hebdomadaire

Attribuer les véhicules par semaine entière, pas par jour.

**Rejetée** pour les raisons suivantes :

- Trop grossier : les usages réels de Renaud incluent des utilisations de quelques jours (2-3 jours pour un déplacement, par exemple).
- Incompatible avec la règle d'exonération LCD à 30 jours consécutifs (le seuil tomberait entre deux semaines).
- Perte de précision sur le prorata fiscal.

### Alternative 3 — Granularité mensuelle

Attribuer par mois.

**Rejetée** pour les raisons triviales : trop grossier, incompatible avec les usages réels, imprécision fiscale excessive.

### Alternative 4 — Granularité variable selon le type d'attribution

Permettre différentes granularités selon le contexte (certaines attributions en jours, d'autres en heures).

**Rejetée** pour les raisons suivantes :

- Complexité de conception démesurée pour un bénéfice nul.
- Règles de conversion ambiguës entre granularités.
- Risque de bugs liés à la cohabitation de granularités.

## Conséquences

### Conséquences positives

- **Alignement parfait avec la fiscalité** : le calcul Floty suit la lettre du CIBS.
- **Modèle de données simple** : une attribution = (véhicule, entreprise, date de début, date de fin) ou (véhicule, entreprise, liste de jours).
- **Interface cohérente** : toutes les vues du cahier des charges § 3 (heatmap globale, vue entreprise, vue véhicule, saisie hebdomadaire) reposent naturellement sur des cellules-jours.
- **Calcul déterministe** : pas d'ambiguïté sur ce qui compte comme « un jour ».
- **Compatibilité bissextile** : la formule `/ 366` en 2024 s'applique uniformément.

### Conséquences techniques

- Une **attribution** dans le modèle de données se représente au minimum par `(vehicule_id, entreprise_utilisatrice_id, date)` — un jour par ligne — ou par `(vehicule_id, entreprise_utilisatrice_id, date_debut, date_fin)` en compactage.
- Le moteur de calcul itère sur l'ensemble des jours d'attribution pour chaque couple.
- La détection du caractère bissextile de l'année se fait automatiquement par une fonction standard (test `annee % 4 == 0 && (annee % 100 != 0 || annee % 400 == 0)`).
- Les indisponibilités (cf. cahier des charges § 2.5) ont également une granularité journalière.
- L'unicité (un véhicule × un jour = une seule entreprise) est une contrainte SQL directe.

### Conséquences produit

- Toute l'ergonomie de saisie (wizard d'attribution rapide § 3.8, saisie hebdomadaire § 3.6, attribution des jours libres § 3.7) est pensée en jours.
- Les compteurs affichés dans les vues (jours utilisés, jours libres, cumul par couple) sont tous en jours.
- Le PDF récapitulatif expose « nombre de jours d'utilisation » par couple véhicule × entreprise.

### Limites assumées

- **Impossibilité de facturer un usage horaire** : si une entreprise utilise un véhicule une demi-journée, Floty compte 1 jour entier. C'est conforme à la fiscalité ; c'est cohérent avec le modèle de location de Renaud.
- **Choix du jour-pivot** : si une attribution dure du 12 mars à 18h au 14 mars à 10h (soit ~40 heures réparties sur 3 jours civils), Floty compte 3 jours. Cohérent avec la lecture « est-ce qu'au cours de ce jour civil, l'entreprise dispose du véhicule ? ».

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0006 — Architecture du moteur de règles
- `project-management/cahier_des_charges.md` § 2.4 (Attribution), § 2.5 (Indisponibilité), § 3.3 à § 3.8 (Vues et saisie)
- `project-management/recherches-fiscales/2024/taxe-co2/decisions.md` Décisions 3, 5, 8 (année bissextile, prorata journalier, jours d'affectation)
- `project-management/taxes-rules/2024.md` R-2024-002 (mécanique du prorata journalier)

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation d'une décision prise dès le brainstorming de cadrage (20/04/2026) et appliquée de manière cohérente durant toute la phase de recherche fiscale. |

# Zones grises et points à valider — Exonérations applicables aux taxes annuelles (CO₂ et polluants) — Exercice 2024

> Ce document détaille les incertitudes, zones grises et décisions à confiance basse ou moyenne identifiées au cours de l'instruction des exonérations pour 2024. Il alimente le fichier transverse `recherches-fiscales/incertitudes.md` qui en présente la synthèse.
>
> **Auteur** : Micha MEGRET (prestataire)
> **Convention de numérotation** : `Z-AAAA-NNN` où `AAAA` = année fiscale concernée, `NNN` = numéro séquentiel par année.

---

## Z-2024-002 — Qualification du modèle Floty au regard de l'exonération LCD (renvoi de clôture)

L'instruction des exonérations a apporté à cette incertitude (initialement ouverte lors de l'instruction de la taxe CO₂) la précision technique sur le mécanisme de la LCD (CIBS art. L. 421-129 et L. 421-141, BOFiP S3 § 180). Le détail consolidé reste tenu dans `2024/taxe-co2/incertitudes.md` (entrée Z-2024-002).

**Statut actualisé le 23/04/2026** : **Résolu**. Après clarification directe avec Renaud sur la nature exacte du montage contractuel, la lecture retenue est **« LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice) »** (et non plus « LLD par défaut »). La pratique de Renaud sur ce point est conforme à la doctrine officielle et n'a jamais fait l'objet de redressement, ce qui constitue une présomption forte de validité. L'exonération LCD est intégrée dans Floty comme règle fiscale standard (R-2024-021 dans `taxes-rules/2024.md`), avec une exigence UI sur l'affichage du cumul par couple aux moments d'attribution.

La Décision 7 de `2024/exonerations/decisions.md` (qui figeait la qualification LLD comme lecture par défaut) reste valable comme **lecture de prudence** au moment où elle a été prise, mais elle est désormais **dépassée par la clarification client** — la lecture LCD avec cumul par couple est la lecture définitive.

---

## Z-2024-010 — Date de référence pour évaluer l'ancienneté du véhicule (exonération hybride 2024 — L. 421-125)

- **Localisation** : `decisions.md` Décision 5 ; `recherches.md` § 4.3 (Exemple A et E), § 8.4, § 9 (Q2).
- **Nature de l'incertitude** : L'aménagement transitoire des seuils de l'exonération hybride 2024 (CIBS art. L. 421-125) prévoit des seuils d'émissions / puissance doublés (120 g/km WLTP, 100 g/km NEDC, 6 CV PA — au lieu de 60 / 50 / 3) pour les véhicules dont l'ancienneté n'excède pas 3 ans depuis la première immatriculation. La question est : **à quelle date évaluer cette condition d'ancienneté** ? 1er janvier de l'année d'imposition ? Date de chaque attribution ? 31 décembre ? Aucune source primaire ne tranche explicitement.
- **Notre choix actuel** : ancienneté évaluée au **1er janvier de l'année d'imposition** (donc 1er janvier 2024 pour l'exercice 2024). Si à cette date le véhicule a strictement moins de 3 ans depuis sa première immatriculation, le régime aménagé s'applique pour toute l'année ; sinon le régime général. Cohérence avec la mécanique annuelle de la taxe et lecture déterministe.
- **Conséquence si erroné** : pour un véhicule passant de < 3 ans à ≥ 3 ans en cours d'année, mauvaise application du seuil sur une partie de l'année. Population concernée restreinte (uniquement les hybrides éligibles immatriculés aux alentours du 1er janvier 2021). Impact financier marginal mais existant.
- **Action attendue** : Validation expert-comptable. Question précise : « Pour l'exonération hybride 2024 (CIBS art. L. 421-125), à quelle date faut-il évaluer la condition d'ancienneté ≤ 3 ans depuis la première immatriculation : 1er janvier de l'année d'imposition, date de chaque attribution, ou autre ? »
- **Statut** : **Ouvert**

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 0.1 | 23/04/2026 | Micha MEGRET | Création initiale lors de la refonte de l'organisation des incertitudes (déplacement depuis le fichier global `recherches-fiscales/incertitudes.md`). Contient Z-2024-010 (date de référence ancienneté hybride 2024), et un renvoi vers Z-2024-002 (enrichissement de la qualification LLD/LCD apporté par l'instruction des exonérations). |
| 0.2 | 23/04/2026 | Micha MEGRET | Mise à jour du renvoi Z-2024-002 pour refléter la **consolidation** opérée par l'instruction du sous-dossier `2024/cas-particuliers/`. Statut maintenu **Ouvert — priorité haute** (validation expert-comptable indispensable). Z-2024-010 sans changement (incertitude propre aux exonérations, hors périmètre de la mission cas particuliers). |
| 0.3 | 23/04/2026 | Micha MEGRET | **Z-2024-002 passée au statut « Résolu »** suite à clarification directe avec Renaud sur la nature exacte du montage contractuel. Lecture définitive : LCD avec cumul annuel par couple (véhicule, entreprise utilisatrice). La Décision 7 de `decisions.md` du présent sous-dossier reste cohérente comme lecture de prudence au moment où elle a été prise mais est dépassée par la clarification client. |

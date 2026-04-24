# Phase 12 — Génération PDF

## Objectif de la phase

Implémenter la **génération effective** du PDF récapitulatif fiscal (cf. ADR-0003, seul document produit en V1). Contraintes :

- **DomPDF** (pas Browsershot — Node indisponible sur Hostinger Business).
- Template HTML/CSS **compatible DomPDF** (pas de flexbox, pas de grid, CSS2.1).
- **Snapshot immuable** persisté avec hash SHA-256.
- **Stockage filesystem Laravel** (disque `local`) avec chemin en BDD.

## Dépendances

Phase 11 (déclarations) terminée.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 12.01 | [Installer `barryvdh/laravel-dompdf` + publier la config](01-install-dompdf.md) | À faire |
| 12.02 | [Configurer disque Laravel `local` pour `storage/app/declarations/`](02-configure-filesystem.md) | À faire |
| 12.03 | [Template PDF HTML `resources/views/pdf/declaration-fiscal.blade.php` (pas de Vue, pas de flexbox, Bootstrap-like table + CSS2.1)](03-template-declaration-pdf.md) | À faire |
| 12.04 | [CSS print PDF (palette Floty minimale, tableaux bordés, en-tête avec logo, pied de page numéroté)](04-pdf-css.md) | À faire |
| 12.05 | [Service `DeclarationPdfRenderer` (wrapper DomPDF → retourne binaire)](05-service-declaration-pdf-renderer.md) | À faire |
| 12.06 | [Service `DeclarationPdfStorage` (écriture filesystem, convention `declarations/{fiscal_year}/{declaration_id}/v{n}-{timestamp}.pdf`)](06-service-declaration-pdf-storage.md) | À faire |
| 12.07 | [Repository `DeclarationPdfWriteRepository` (persist ligne `declaration_pdfs` avec hash + snapshot)](07-repository-declaration-pdf-write.md) | À faire |
| 12.08 | [Action `GenerateDeclarationPdfAction` (orchestration : calculate + snapshot + render + storage + persist en transaction)](08-action-generate-declaration-pdf.md) | À faire |
| 12.09 | [Controller invocable `User/Declaration/GenerateDeclarationPdfController`](09-controller-generate-pdf.md) | À faire |
| 12.10 | [Bouton « Générer PDF » dans la page Show de Declaration + feedback visuel pendant processing](10-ui-generate-pdf-button.md) | À faire |
| 12.11 | [Téléchargement des PDF historiques (signed URL ou route protégée)](11-download-pdf-history.md) | À faire |
| 12.12 | [Tests Feature : génération PDF complète avec vérification hash + contenu snapshot](12-tests-pdf.md) | À faire |
| 12.13 | [Tests visuel manuel : PDF réel ouvre dans Acrobat Reader sans erreur, contenu fiscal correct](13-manual-pdf-visual-check.md) | À faire |

## Critère de complétion

- Bouton « Générer PDF » dans une déclaration au statut `verified` produit un PDF téléchargeable.
- Le PDF contient : en-tête company + année fiscale, tableau détaillé par couple (vehicle × company) avec jours, taxe CO₂, taxe polluants, total ; pied de page avec numérotation et date de génération.
- Une entrée `declaration_pdfs` est créée avec tous les hash.
- Le fichier est présent dans `storage/app/declarations/{year}/{declaration_id}/`.
- Le PDF est **immuable** : regénérer crée une nouvelle ligne v+1, la précédente reste.

## Documents liés

- [`docs/pdf-template-constraints-dompdf.md`](../../docs/pdf-template-constraints-dompdf.md) — limitations CSS DomPDF et comment les contourner.
- [`docs/pdf-template-floty.md`](../../docs/pdf-template-floty.md) — structure visuelle précise du PDF récapitulatif fiscal V1.
- [`docs/pdf-storage-filesystem.md`](../../docs/pdf-storage-filesystem.md) — arborescence, nommage, cohérence BDD ↔ filesystem.

## Références

- ADR-0003 (PDF immuables)
- `modele-de-donnees/02-schema-fiscal.md` § 3
- `architecture-solid.md` § 4 — exemple détaillé `GenerateDeclarationPdfAction`
- CDC § 5.7 (contenu PDF récapitulatif)

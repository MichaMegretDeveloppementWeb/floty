---
name: floty-design-system
description: Design system for Floty, a B2B shared-fleet + French vehicle-tax management web tool. Dense, editorial, French-language UI built on DM Sans + DM Mono, slate + blue palette, and lucide outline icons. Use when designing or implementing any Floty screen, PDF, or component.
---

The full design system is in project-management/design-system/. See README.md and colors_and_type.css in this folder.

# Floty design system — agent skill

You are designing or coding for **Floty**: a desktop-first B2B web app for managing a shared vehicle fleet across ~30 user companies and calculating the French CO₂ + pollutant taxes that replaced TVS in 2022.

**First read** `README.md` in this folder. It defines the content fundamentals (French tone, number/date formatting, terminology), the visual foundations (palette, type, spacing, layout), and the iconography rules. Do not invent tokens — use what's in `colors_and_type.css`.

## Non-negotiables

1. **French only.** All interface text, labels, empty states. Use `vous`. No emoji.
2. **Numbers:** thousands separator = non-breaking space (`142 840 €`); decimals = comma (`4,2 %`); currency suffixed with space + `€`.
3. **Palette:** slate for chassis, a single blue scale for data + interactive, emerald/amber/rose for semantic ONLY, 8 saturated company chips for entities (never UI chrome). No gradients except the one quiet slate-900 → slate-800 exception on AI-conseil surfaces.
4. **Typography:** DM Sans (400/500/600/700) + DM Mono (400/500). Px-based scale — the UI is dense. Never go below 11 px for labels, 13 px for body.
5. **Iconography:** `lucide` outline, `strokeWidth: 1.75`. Icons pair with text except the `MoreHorizontal` overflow trigger.
6. **No shadows at rest.** `shadow-sm` on hover for fiscal cards; `shadow-2xl` only on drawers/modals.
7. **Base unit = 4 px.** Radii: `rounded-lg` (8) on interactive, `rounded-xl` (12) on container cards, `rounded-[3px]` on heatmap cells, `rounded-full` on avatars.
8. **No bounce, no spring, no fade-ins on mount.** Transitions are 150 ms linear on color/border.

## Mandatory motifs

- **Eyebrow + title + subtitle** at the top of every view.
- **KPI card anatomy:** uppercase label → 22–28 px number → 12 px caption.
- **Density heatmap** uses the 8-step blue scale (white → `blue-950`). Cell is 20×28 px with 1 px gap.
- **Status pills:** 50-bg / 200-border / 700-text. Sentence-case, 1 word: `Brouillon`, `Prête`, `Envoyée`.
- **Plate + SIREN are monospace.** Plates uppercase with dashes: `EH-142-AZ`.

## When writing HTML or React

- Import `colors_and_type.css` (or inline its tokens into Tailwind config).
- Load lucide via `https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js` for vanilla HTML, or `lucide-react` for React.
- Default container: `max-width: 1400px; padding: 32px;` on main; 240 px fixed sidebar; 64 px sticky top bar.
- See `floty_ui_kit.html` for a canonical assembly of header, sidebar, KPIs, heatmap, alerts, and fiscal table.

## When asked for something NOT covered

- Derive from existing tokens, don't invent. If a new semantic color is needed, restate the case and ask; don't default to adding a new hue.
- Mobile: allowed to degrade gracefully; desktop is canonical.
- Print/PDF: same type + palette; no shadows; explicit page breaks between sections.

# Floty Design System

> A dense, editorial, professional design system for a B2B fleet-management web tool.

## What is Floty?

Floty is a B2B web application for managing a **shared vehicle fleet** across multiple companies belonging to the same group. It helps a fleet manager:

1. Plan and track day-by-day usage of ~100 vehicles across ~30 user companies
2. Visualize a full year of fleet utilisation at a glance (heatmap views)
3. Calculate the French **CO₂ tax** and **pollutant tax** (the two taxes that replaced TVS in 2022) owed by each user company, pro-rated by days of use
4. Generate fiscal declaration PDFs that back each year's tax filings
5. Keep multi-year history of vehicles, drivers, companies and attributions

Floty is **not** a marketing site — it's a dense daily work tool. Every pixel serves data.

- **Language:** French (interface only, no i18n)
- **Platform:** Desktop-first web app, with reasonable responsive down to tablet
- **Users (V1):** single power user ("Renaud") — the fleet manager
- **Framework (prototype):** React + Tailwind + lucide-react + recharts

## Sources used to build this system

| Source | Path / Link |
|---|---|
| Existing prototype | `uploads/fleet-app.jsx` (~2000 lines React) |
| Product spec | `uploads/cahier_des_charges.md` (full functional spec, fiscal rules, UX principles) |

No Figma, no separate brand guide — the React prototype is the source of truth for visual language. Everything in this design system is reverse-engineered from it and from the spec's UX principles.

## Index

| File / folder | What's in it |
|---|---|
| `README.md` | You are here — context, content + visual foundations, iconography, index |
| `SKILL.md` | Agent Skill manifest so this design system can be imported into Claude Code |
| `colors_and_type.css` | CSS variables: color scales (slate, blue, semantic), typography tokens, radius, shadow, spacing |
| `fonts/` | DM Sans + DM Mono (self-hosted via Google Fonts CDN in CSS) |
| `assets/` | Logo (Floty mark + wordmark), SVG brand primitives |
| `preview/` | Design-system cards shown in the Design System tab (tokens, components, specimens) |
| `floty_ui_kit.html` | Dashboard composition — all tokens and components assembled into a working Floty screen |

---

## CONTENT FUNDAMENTALS

### Language and tone

- **French only.** Interface text, labels, errors, empty states — all in French. No franglais unless the word is already a loanword in common French tech usage (ex: "dashboard" is OK, "clic" is better than "click").
- **Voice:** neutral-professional, like a quiet colleague. No exclamation points. No emoji. No personality quirks.
- **You/we:** mostly impersonal. When addressing the user directly, use **second-person singular "vous"** (formal). The dashboard greeting is a controlled exception: *"Bonjour, Raphaël."* + *"Voici ce qu'il se passe sur votre flotte aujourd'hui…"*
- **Density-first copy:** labels are short, nouns preferred over verbs. Section labels are ALL-CAPS with wider tracking: `TABLEAU DE BORD`, `VUE D'ENSEMBLE`, `FISCALITÉ`.

### Casing rules

| Context | Casing | Example |
|---|---|---|
| Page titles (`h1`) | Sentence case | *Vue globale*, *Déclarations fiscales* |
| Nav section headers | UPPER CASE + tracking | *PLANNING*, *DONNÉES*, *FISCALITÉ* |
| Column / card labels | UPPER CASE + tracking | *TAUX D'OCCUPATION*, *VÉHICULES ACTIFS* |
| Inline labels, inputs | Sentence case | *Rechercher véhicule, entreprise, conducteur…* |
| Buttons | Sentence case, short verb or noun phrase | *Nouvelle attribution*, *Exporter*, *Ajouter un véhicule* |
| Status pills | Sentence case, 1 word preferred | *Brouillon* · *Prête* · *Envoyée* |
| Eyebrow labels above a title | UPPER CASE + tracking | *NOUVELLE ATTRIBUTION* above *Attribuer un véhicule* |

### Numbers, dates, units

- **French thousands separator:** non-breaking space — `142 840 €`, `1 420 €`, `36 500 cellules`.
- **Decimals:** comma, never dot — `4,2 j/semaine`, `68,0 %`.
- **Currency:** space + `€` sign **after** the number: `420 €`, `1 240 €`.
- **Dates:** French long form for display — *4 février 2026*, *jeudi 16 avril 2026*. Short-form tables use `15 juin 2023`. Never `02/15/2023`.
- **Immatriculations** (plates) are displayed in **monospace**, uppercase, with dashes: `EH-142-AZ`, `FB-671-TU`.
- **SIREN** is displayed monospace with non-breaking thin spaces every 3 digits: `123 456 789`.
- **Week numbers** are prefixed `S` in compact contexts: `S17`, or spelled out in headers: *Semaine 17*.

### Terminology (use exactly these words)

| Domain term | Always written as |
|---|---|
| Vehicle types | **VP** (voiture particulière), **VU** (véhicule utilitaire) |
| Taxes | *Taxe CO₂*, *Taxe polluants* (never "TVS", that's the old name) |
| Methods | *WLTP*, *NEDC*, *Puissance administrative* |
| Entity names | *véhicule*, *entreprise utilisatrice*, *conducteur*, *attribution*, *indisponibilité* |
| Declaration states | *Brouillon* → *Vérifiée* → *Générée* → *Envoyée* |

### Micro-copy patterns

- **Eyebrow + title + subtitle** at the top of every view:
  > `FISCALITÉ` · *Déclarations fiscales* · *Taxes sur véhicules affectés à des fins économiques · Année 2025*
- **KPI card:** short ALL-CAPS label, big number, tiny caption explaining the context. No decoration.
- **Empty cells** in planning use a dashed border + `+` glyph — never the text "Empty" or "Ajouter".
- **Tooltips** are short factual — `S17 · 5 j/7`, never sentences.
- **Confirmation** is implicit; a thin toast with **Annuler** appears for 10 s. Destructive actions explicitly confirm.
- **AI conseil** cards are rare and labelled `CONSEIL IA` in an amber eyebrow — the only place amber ink appears on text.

### Example phrases (lifted from the real UI)

- Dashboard greeting: *"Bonjour, Raphaël. Voici ce qu'il se passe sur votre flotte aujourd'hui — jeudi 16 avril 2026."*
- Search placeholder: *"Rechercher véhicule, entreprise, conducteur…"*
- Reading guide on heatmap: *"Fond = densité globale (tous utilisateurs confondus) · Chiffre = jours utilisés par [Entreprise]"*
- Legal footer on PDFs: *"Ce document est un récapitulatif détaillé servant de pièce justificative…"*
- Settings warning: *"Les barèmes et formats de documents doivent être validés annuellement par votre expert-comptable avant génération des déclarations officielles."*

---

## VISUAL FOUNDATIONS

### Palette

**Neutral is the system.** Slate is the chassis: `slate-50` for page background, white for surfaces, `slate-200` for borders, `slate-500` for secondary text, `slate-900` for primary text and strong accents. No pure black, no pure gray — always slate.

**Blue is data.** A single blue scale (Tailwind `blue-50 → blue-950`) drives:
- The **8-step density heatmap** (0 jours → 7/7 jours) — see `preview/heatmap-density.html`
- Interactive-info pills (`bg-blue-50 text-blue-700`)
- The `Prête` declaration status

**Semantic colors** are restricted to one 3-tone combo each (50 bg / 200 border / 600-700 text):
- `emerald` → success, positive trend, exonération confirmée
- `amber` → warning, AI hint eyebrow, VU type badge
- `rose` → destructive or overdue (TODO items)

**Company chips** are 8 saturated one-offs (`#4f46e5`, `#059669`, `#d97706`, `#e11d48`, `#7c3aed`, `#0d9488`, `#ea580c`, `#0891b2`). These are attached to *entities*, not design — they appear as 6-10 px rounded squares with white 2-3-letter code inside. Never use them for UI chrome.

**No gradients** anywhere, except ONE controlled exception: the AI-conseil card uses `bg-gradient-to-br from-slate-900 to-slate-800` — a near-imperceptible gradient that reads as a quiet dark surface, not as a gradient.

### Typography

- **Sans:** **DM Sans** — 400 / 500 / 600 / 700. Default for everything.
- **Mono:** **DM Mono** — 400 / 500. Used for plates, SIREN, numeric table cells, kbd tags, CO₂ values.
- **Scale (px-based, because the UI is dense):**
  - H1 view title: `26–28 px / 600 / tracking-tight / leading-none`
  - H2 card title: `15 px / 600`
  - Body: `13–13.5 px / 400`
  - Dense body / tables: `12.5–13 px / 400`
  - Caption / label: `11–12 px / 500–600 / tracking-wider / uppercase when eyebrow`
  - KPI number: `22–28 px / 600 / tracking-tight / leading-none`
  - Micro-label (column header, spec label): `10–11 px / 600 / tracking-wider / uppercase`
- **No italic.** No underlines except on `<a>` hovers (and even then we prefer color change).

### Spacing

- **Base unit: 4 px.** Every value is a multiple of 4.
- Scale in use: `4, 8, 12, 16, 20, 24, 32, 40, 48, 60 px`.
- Density is tight — `py-2`/`py-3` inside tables, `p-5`/`p-6` inside cards, `px-10 py-8` for main content region, `w-60` for the sidebar.

### Borders, radii, shadows

- **Border:** 1 px `#e2e8f0` (slate-200). Horizontal dividers use `slate-100`. Hover elevates to `slate-400`.
- **Radius:** `rounded-lg` (8 px) on everything interactive — buttons, inputs, pills, cells. `rounded-xl` (12 px) on large container cards. `rounded-full` on avatars and status dots. `rounded-[3px]` on heatmap cells (tight squares).
- **Shadows:** almost none. Two controlled uses:
  - `shadow-sm` appears on card hover in fiscal grid (*hover:shadow-sm*).
  - `shadow-2xl` only on drawers and modals (overlay panels).
- **No inner shadow, no neumorphism, no glow.**

### Backgrounds & surfaces

- **Page:** solid `bg-slate-50` (`#f8fafc`). No texture, no pattern, no image.
- **Cards:** solid white, 1 px slate-200 border. Never shadowed at rest.
- **Table zebra:** *not* used — instead, the table header has a `bg-slate-50/60` tint and total rows use `bg-slate-50`.
- **Drawer backdrop:** `bg-slate-900/20` + `backdrop-blur-[2px]` — very light. Modals go stronger: `bg-slate-900/40 + blur-sm`.

### Transparency & blur

- Sticky top bar uses `bg-slate-50/85 backdrop-blur` to let scrolling content show through subtly.
- Drawer/modal overlays use slate-900 at 20-40 % opacity + 2-4 px blur. That's the only blur in the system.

### Animation

- **Transitions:** `transition-colors` and `transition-all` at default timing (~150 ms). No bespoke easing curves.
- **Hover states:**
  - Text links: darken ink (`text-slate-500 → text-slate-900`).
  - Buttons primary: `bg-slate-900 → bg-slate-800`.
  - Cards: border darkens (`border-slate-200 → border-slate-400`), sometimes `+ shadow-sm`.
  - Heatmap cells: `hover:scale-110 hover:z-10 hover:ring-2 hover:ring-slate-900 hover:ring-offset-1` — the one place we use scale.
- **Press / active:** use darkened bg (`bg-slate-800`) — no press-shrink.
- **No bounce. No spring. No fade-ins on mount.** Focus rings use `focus:ring-2 focus:ring-slate-100` — a subtle halo, not a bright glow.

### Layout rules

- **Fixed sidebar** 240 px, always visible, scrolls independently.
- **Sticky top bar** over main content.
- Main content max-width around `1400 px` on wide screens, flush otherwise. Padding `px-10 py-8`.
- **Grids:** dashboard KPIs → 4 cols. Companies grid → 2 cols. Declarations grid → 3 cols. Vehicle specs → 6 cols. All gap-4.
- **Heatmap grid:** 52 columns × N rows (one per vehicle). Each cell is a 20×28 px square with 1 px gap. Mobile responsive is allowed to degrade; desktop is canonical.

### Iconography flavor

- **lucide-react**, outline, **strokeWidth: 1.75**. Consistent, thin, friendly without being soft. Sized at 12–16 px in-line.
- Icons never appear alone as actions — they pair with text, or serve as decorative glyphs in an alert row's color chip.
- Single exception: the `MoreHorizontal` (⋯) menu trigger, which is icon-only.

### Card anatomy (the repeating motif)

```
┌──────────────────────────────────────────┐
│  LABEL (10-11 px, uppercase, slate-500)  │
│  28 px value                              │
│  12 px caption, slate-500                 │
└──────────────────────────────────────────┘
  bg-white · border slate-200 · rounded-xl · p-5
```

That's it. Same anatomy powers KPIs, spec cells, mini-stats, vehicle rows — the entire system is this one card, resized.

---

## ICONOGRAPHY

### Source

- **Library:** [lucide-react](https://lucide.dev) (included in the prototype; MIT-licensed; CDN-available for HTML mocks via `https://unpkg.com/lucide@latest`).
- **Style:** outline only. `strokeWidth={1.75}` (thinner than default 2, heavier than 1.5) — critical to Floty's editorial feel.
- **Sizes:** 12 px in dense rows, 13-14 px in buttons, 15-16 px in sidebar/top-bar, 20-24 px in illustrative spots.
- **Color:** always inherit `currentColor`. Never filled.

### Canonical icons used in Floty

| Area | Icon | Notes |
|---|---|---|
| Dashboard | `LayoutDashboard` | |
| Planning (global) | `Grid3x3` | |
| Planning (by company) | `Building2` | |
| Planning (by vehicle) / fleet | `Car` | |
| Weekly input | `Table2` | |
| Companies & drivers | `Users` | |
| Fiscal declarations | `Receipt` | |
| Analytics | `BarChart3` | |
| Settings | `Settings` | |
| Search | `Search` | with `⌘K` kbd chip |
| Year nav / pagination | `ChevronLeft` `ChevronRight` | |
| Expand | `ChevronDown` | |
| Export | `Download` | |
| Import | `Download` rotated 180° | |
| New / Add | `Plus` | |
| Info tooltip | `Info` | |
| Warning | `AlertCircle` | |
| Success / AI hint | `Sparkles` / `Zap` | |
| Trend up | `TrendingUp` `ArrowUpRight` | |
| Menu / overflow | `MoreHorizontal` | |
| Drawer chevrons | `ChevronsRight` | at end of alert rows |
| Close | `X` | drawers / modals |
| Brand mark | `CircleDot` | placeholder — see `assets/` for the real Floty mark |

### Emoji

**Not used.** Floty is a fiscal tool; emoji would undermine the editorial tone.

### Unicode glyphs

The only non-letter characters that appear inline:
- `⌘` `⌥` `⇧` — Cmd-palette keyboard hints (inside `<kbd>` chips, monospace)
- `→` `↓` `↑` — trend / arrow indicators
- `·` — separator between meta fields
- `€` — currency suffix, with a space before it
- `%` — no space before

### SVGs vs. PNGs

Everything in `assets/` is SVG. No PNG icons, no icon font. Logos are SVG too, optimised and `currentColor` where possible.

### Substitution notice

The prototype uses a `CircleDot` lucide icon as a placeholder "logo" in the sidebar. This design system ships a proper **Floty wordmark + mark** (see `assets/floty-logo.svg` and `assets/floty-wordmark.svg`). If you have a real brand logo, please replace these files — they are a design proposal, not an official asset.

### Font substitution

DM Sans and DM Mono are loaded from Google Fonts (the prototype imports them at runtime). No local `.ttf` files are bundled; this is intentional for web use. If you need offline/print assets with these fonts, download from https://fonts.google.com/specimen/DM+Sans and https://fonts.google.com/specimen/DM+Mono and drop the `.ttf` files into `fonts/`.

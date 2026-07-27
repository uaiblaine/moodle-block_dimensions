# Block kit — Dimensions block (`block_dimensions`)

An **as-is visual replica** of the plugin's single learner-facing surface: the Dimensions block as it
renders on a dashboard — the header and search, the client-built filter bar, the two card types with
their competency trail, and every load / error / empty state in between.

It follows the method of the sibling plugin's kits (`local/dimensions/docs/learner-kit/` and
`docs/design-kit/`): every `.html` is a self-contained preview carrying a `@dsCard` marker on its
first line, and every significant element gets an **ID badge** (`.idb`) in a caption line for review.

Each screen inlines one canonical `--bk-*` **token block** and renders the markup twice — a **light**
panel (`.panel`) and a **dark** panel (`.panel.dark`). Both are **as-is**: `styles.css` genuinely
ships a `.theme-dark` / `body.dark` skin, so the dark panel is a second faithful rendering, not a
proposal. The markup is written once with `var(--bk-*)`; only the token values differ between panels.
Two screens deviate and say so on their label: `states.html` shows a state matrix rather than a
screen, and `responsive.html` pairs *mobile* against *narrow block column* instead of light against
dark.

**As-is only — and the as-is moved.** There are no to-be panels. On **2026-07-27** the plugin's
palette was migrated to Moodle DS (every colour, both card gradients, one consolidated focus ring)
and the horizontal card's title/star collision was fixed; the kit was then re-baselined, so what the
panels show is what `styles.css` ships today. `token-migration.md` is the record of that change and
of the five questions deliberately left open.

## Foundations
| File | What it is |
|---|---|
| `tokens.html` | The real `styles.css` palette tokenized as `--bk-*`, shown light │ dark: primary and accents, surfaces and neutrals, the two card gradients, status/favourite/feedback colours, radii and the type scale. Retired values are struck through so the move is readable. Also the source of the canonical scaffold every screen inlines. |
| `token-migration.md` | Repo-only companion: the record of the **implemented** Material/Google → Moodle DS migration — what moved, to what, at which `styles.css` line, plus the four contrast corrections the swap forced, the four defects fixed in passing, the eight contrast repairs that completed the dark skin, and the five questions left open for a decision. |
| `screens/states.html` | Interactive states and focus as they really ship: hover, focus, high contrast, reduced motion, print. Now documents **one** focus ring per skin — it used to be four in light and two in dark. |

## Screens (`screens/`)
| File | Screen |
|---|---|
| `screens/shell.html` | Block shell (`BLK`) — the only server-rendered HTML: heading, search row, filter toggle, loading, error + retry, section headers, grids, empty state, live regions, no-JS notice |
| `screens/filters.html` | Filter bar (`FLT`) — favourites/all pills with counts, the pill platter with its sliding indicator, mask and paddles, the dropdown variant, clear filters |
| `screens/plan-card-vertical.html` | Plan card (`PLN`) — vertical layout: image on top, tags, access pill, favourite star, title, count text, horizontal competency trail with continuation stubs |
| `screens/plan-card-horizontal.html` | Plan card (`PLN-H`) — horizontal layout: 180px image column, vertical trail, access pill top-left, star top-right, and the container-query stack |
| `screens/competency-card.html` | Competency card (`CMP`) — gradient plus halftone, custom colours, tags, access pill, 2-line clamped title |
| `screens/empty-error.html` | States (`BLK` · `GST`) — loading, error, both empty states, the assertive favourite-error announcement, no-JS, and the two-phase-loading ghost card |
| `screens/responsive.html` | Responsive (`RSP`) — the 575.98px breakpoint, the collapsible filter panel, and the container queries that respond to the block column rather than the viewport |

`screens/states.html` also lives in this folder but is grouped as a **Foundation** (see the table
above), because it documents the interaction vocabulary rather than a surface. Its `@dsCard` group
says `Foundations`, which is what the Design System index reads.

## ID convention
Format `PREFIX-SECTION[-NN]`, **stable** across re-syncs. Prefixes:

| Prefix | Surface |
|---|---|
| `BLK` | the block shell — header, search, sections, grids, load/error/empty states, live regions |
| `FLT` | the filter bar (built by `filters.js`, not by a mustache template at runtime) |
| `PLN` / `PLN-H` | plan card, vertical / horizontal layout |
| `CMP` | competency card |
| `GST` | the ghost card ("View more items") |
| `STA` | interaction states |
| `RSP` | responsive behaviour |

Every interactive element and every meaningful static region gets an ID; pure layout wrappers do not.
An element that exists in both card layouts is shown under both prefixes — the plan title is
`PLN-TITLE` in the vertical screen and `PLN-H-TITLE` in the horizontal one, because the two branches
of `plan_card.mustache` are separate markup, not one shared block.

## Field maps (`maps/`) — repo-only
An as-is inventory per surface: each element with its **stable ID**, label, type, **source**
(`mustache:line` / `js:line` / `styles.css:line`), the data it carries, and its business rule. These
stay in the repo; they are not synced to Claude Design.

| File | Surface |
|---|---|
| `maps/block.md` | `BLK` · `FLT` · `GST` · `RSP` — shell, filter bar, ghost card, responsive |
| `maps/cards.md` | `PLN` · `PLN-H` · `CMP` — both card types and the competency trail |

**Foundations are not mapped**, by method — the same split the sibling kits use, where `maps/` covers
screens and the Foundations files stand on their own. So `tokens.html` has no map, and neither does
the `STA-*` family in `states.html`: a state is not a field, it has no data and no business rule, and
its "source" is a CSS rule the file already quotes inline. Every ID in the seven mapped surfaces
resolves to a row; the twelve `STA-*` badges resolve to `states.html` itself.

## Code mapping
- `block_dimensions.php` → `\block_dimensions\output\summary` → `summary.mustache`. The block renders
  only for logged-in non-guests and only when `core_competency` is enabled; `has_content()` returns
  false when the user has no active plan, so the block does not appear at all.
- `summary.mustache` is a **shell**. `export_for_template()` ships `labelsjson`,
  `filtersettingsjson` and a handful of flags — no cards.
- `amd/src/filters.js` calls the web service `block_dimensions_get_block_dataset` and renders
  `plan_card` / `competency_card` client-side through `core/templates`. **Two-phase loading**: the
  first request asks for favourites only, then `loadgroup` (`plan` / `competency`) fetches the missing
  group. `amd/src/state.js` holds the pure filter/search predicates;
  `amd/src/filter_tabs_nav.js` adds the platter's mask, sliding indicator and scroll paddles.
- The **filter bar is not a template at runtime** — `renderFilterControls()` builds it by string
  concatenation. `templates/filters.mustache` is a documentation-only mirror kept in sync; editing it
  changes nothing on screen. The kit replicates the JS output, not the template.
- Card metadata (images, tags, colours, trail data) comes exclusively from `local_dimensions` MUC
  caches — `competency_metadata_cache`, `template_metadata_cache`, `plan_trail_cache`. This plugin
  owns no metadata of its own.
- Favourites are core `favourite` rows in the **user context** under component `block_dimensions`,
  itemtypes `plan` / `competency`, written by `block_dimensions_toggle_favourite`.
- Styles: `styles.css` (2246 lines, all of it scoped under `.block_dimensions`), light plus a
  `.theme-dark` / `body.dark` skin, `prefers-contrast: high`, `prefers-reduced-motion: reduce` and
  `@media print` variants throughout.

## Security invariant the replica preserves
`bgcolor`, `textcolor` and `imageurl` are interpolated into `style="..."` attributes on the cards.
Mustache `{{ }}` escaping does **not** protect a CSS context, so every value bound to a style
attribute passes `dataset_provider::sanitize_color()` (hex only) or `sanitize_image_url()`
(`clean_param PARAM_URL`), and the web service returns URLs as `PARAM_URL`, never `PARAM_RAW`. The
card screens show the custom-colour variants; the maps record the guards. Keep both in sync if a new
style-bound field is added.

## Release packaging
`docs/` is **excluded from the release zip** via `.gitattributes` (`/docs export-ignore`). The kit is
versioned for collaboration; `git archive HEAD` — which builds the installable zip — ships only what
Moodle needs at runtime. Verify with `git check-attr export-ignore -- docs`.

## Note on writing files here
The plugin's CI runs a development-leftover checker that greps **every** file in the repo, docs
included, and fails the build on a stray to-do or merge-conflict marker. Never write those tokens
literally in this folder.

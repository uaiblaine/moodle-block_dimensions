# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Changed
- **Palette migrated to Moodle DS.** Every colour in `styles.css` now matches the palette
  `local_dimensions` already shipped: primary `#0f6cbf` (replacing Google blue `#1a73e8`, and
  absorbing the three other focus blues `#005fcc`, `#0a58ca` and `#1765cc` into one ring),
  greys onto the Bootstrap scale (`#6c757d`, `#495057`, `#dee2e6`, `#e9ecef`, `#ced4da`),
  success onto BS5 `#198754` / `#0f5132`, and the dark skin onto BS5 dark tokens
  (`#6ea8fe`, `#9ec5fe`, `#212529`, `#75b798`, `#ea868f`). No Google/Material literal remains.
- **Both card gradients migrated, mechanism unchanged.** The plan card adopts the sibling's
  exact gradient `linear-gradient(135deg, #0f6cbf 0%, #0a5aa0 100%)`; the competency card keeps
  its warm three-stop gradient *and* its halftone dot overlay, moved onto the orange family
  `local_dimensions` keeps as its brand accent (`#ffc107 → #fd7e14 → #e8590c`). The section
  header gradient follows to `#0f4d85 → #0f6cbf`.
- **Card tag chips** default to `#fd7e14` with dark `#212529` text (Bootstrap's warning-badge
  convention). The previous white-on-`#ef4136` pairing was 3.83:1 and failed WCAG 1.4.3.

### Fixed
- **Horizontal plan card: the title no longer runs under the favourite star.** This layout moves
  the star to the card's top-right, but nothing reserved that space, so a long first line passed
  beneath it. The title now carries `padding-right: 1.75rem`.
- **Contrast regressions caught while migrating** — inactive filter-pill, select and count-badge
  text moved to `#495057` on the `#e9ecef` platter (was 3.95:1), and the search placeholder and
  mobile filter-toggle label to `#5c636a` on their `#f8f9fa` fill (was 4.45:1). All text and
  non-text pairs in the block now meet WCAG 2.1 AA.
- Malformed four-argument `rgb(228, 228, 228, 0.44)` on the plan-card border replaced with
  `rgba(0, 0, 0, 0.125)`, matching the competency card; the near-white `#FFFEFC` plan-card
  background normalised to `#fff`.
- **Pending-trail marker now meets non-text contrast.** Its ring was 2px `#adb5bd` — 2.07:1 against
  white, under the WCAG 1.4.11 floor, and a source comment wrongly asserted "≈ 3.1:1". It is now
  1px `#6c757d`, 4.69:1: darkening alone would have made pending steps read heavier than completed
  ones, so the stroke thinned in the same change (1.4.11 constrains the ratio, not the thickness).
  Outer geometry is unchanged, so nothing shifts.
- **Dark mode completed.** Sweeping for that same class of failure found five more, four of them
  rules that had never been given a dark counterpart: the pending ring in dark (`#6c757d` on
  `#343a40`, 2.45:1 → `#adb5bd`, 5.55:1); the completed marker and both completed connectors
  (`#198754`, 2.54:1 → `#75b798`, 4.92:1); the trail-label hover (`#0f6cbf`, 2.15:1 → `#6ea8fe`,
  4.76:1); the select focus ring (2.88:1 → `#6ea8fe`, 6.39:1); `.dims-filter-count`, which had no
  dark rule at all and sat on the dark platter as a light island (now `#495057` / `#dee2e6`,
  6.28:1); and the mobile filter toggle, likewise ruleless, which showed a light disc on the dark
  sticky header (now `#212529` / `#495057` / `#adb5bd`, `#6ea8fe` on-state and focus).
- **Every focus ring now flips with the skin.** Consolidating the four focus blues onto `#0f6cbf`
  left eleven of the fourteen indicators with no dark counterpart — `#0f6cbf` measures 2.15:1 on the
  `#343a40` card and 2.88:1 on the `#212529` page, both under the 3:1 floor. One block now moves all
  of them to `#6ea8fe` (4.76:1 / 6.39:1), including the plan card's glow and the filter pill's inset
  ring. Verified in a browser, not by pattern-matching.
- **Section-header dark and print overrides were inert.** `-webkit-text-fill-color: transparent` on
  the base rule beats `color`, so both overrides painted nothing. They now clear the gradient fill
  first, so the dark `#dee2e6` and the print `#000` actually apply.
- **The Access / Continue pill's hover feedback never fired.** Its lift and its focus ring selected
  the pill as a descendant of the card link, but both templates make it the link's sibling subtree,
  so neither rule had ever matched — and the article-scoped block meant to replace them only set
  `opacity: 1`, itself a no-op. All three were replaced by one block on `.plan-card:hover` /
  `.competency-card:hover` / `:focus-within`, where `stretched-link` makes the states bubble. Hover
  and keyboard focus share the lift on purpose: the card already draws the focus ring, so the pill
  no longer carries a competing one. Dark deepens the hover shadow to `rgba(0, 0, 0, .5)` (its rest
  shadow is already `.35`), and `prefers-reduced-motion` drops the transform.

No text or non-text pair the block controls fails WCAG 2.1 AA in either skin, with one documented
exception under `prefers-contrast: high`, which still assumes a light background.

### Added
- `docs/block-kit/` — an as-is design kit (tokens, eight screen replicas, two field maps) for the
  block's UI, excluded from the release zip via `.gitattributes`.

## [2.0] - 2026-07-13

Macro view of everything since v1.0 — per-change detail lives in the commit history.

### Added
- **Return-to-Plan integration**: the `block_dimensions_set_return_context` web service fires
  before trail navigation, and the dataset carries the plan association — so courses opened
  from the block get a working "Return to plan" button from `local_dimensions`.
- **Two-phase loading**: `block_dimensions_get_block_dataset` gained a `loadgroup` parameter;
  the block renders the user's favourites first and fetches each remaining card group on
  demand ("Show all" pill, ghost card, search, or automatically for groups without
  favourites).
- **Section headers**: optional `enable_section_headers` setting rendering
  language-customisable two-line headings above the plan and competency card groups.
- **Filter UX package**: clear-filters button, horizontal-scrolling pill navigation with
  paddles, adaptive card grid, mobile refinements, and admin-configured custom-field display
  names as the filter labels.
- **Full accessibility overhaul (WCAG 2.1 AA)**: real links replace simulated controls
  (cards use the stretched-link pattern, trail items are real anchors), filter pills became
  radiogroups with roving tabindex and Home/End, live regions announce results and errors,
  focus is preserved across re-renders, every control gained a real accessible name, plus
  contrast and focus-indicator fixes.
- **Favourites hardening**: ownership/existence validation on toggle, orphan cleanup on
  uninstall (`db/uninstall.php`), privacy provider covering the user data.
- **Testability**: `state` AMD module extracted from `filters.js`; PHPUnit suites for the
  dataset provider, sanitisers and web-service contract; CI dependency wiring for
  `local_dimensions`.
- **CI**: moodle-an-hochschulen reusable workflow — static checks plus PHPUnit and Behat
  runtime legs; full PHP × DB matrix on Moodle 5.02, single-database legs on
  5.01/5.00/4.05.

### Changed
- **`local_dimensions` dependency pinned**: `version.php` now requires `local_dimensions`
  2026071306 (its v2.0) or later instead of `ANY_VERSION` — the block depends on its cache
  classes, constants and return-context API.
- **Web-service contract**: the single `hasnonfavourites` flag in `get_block_dataset`'s
  response was replaced by per-group `hasnonfavouriteplans` / `hasnonfavouritecompetencies`.
- `dataset_provider` modularised into focused helpers; card metadata (images, tags,
  colours) is read exclusively from the `local_dimensions` caches — no direct
  customfield/File-API metadata reads left.
- Plugin maturity raised from BETA to STABLE.

### Security
- Card colours and image URLs are sanitised server-side before reaching inline styles
  (defense in depth on top of the `local_dimensions` field restrictions).

### Removed
- ~350 lines of unreachable code left over from the cache-based metadata refactor.

### Fixed
- Web-service return structures silently stripping undeclared fields (filter labels,
  favourite button aria attributes) via `clean_returnvalue`.
- `get_block_dataset` crashing outside page rendering (`$PAGE->context was not set`).
- Favourite star positioning, `favouritesdisabled` gating, and assorted lint/CI debt the
  previous workflow never actually ran.

## [1.0] - 2026-03-22

First stable release: learning plans and competencies rendered as visual cards (images,
colours and tags from the Dimensions custom fields), tag filters and search, the competency
trail, favourites with filter pills, ghost cards, and display-mode routing into the
`local_dimensions` learner views — backed by the `get_block_dataset` web service.

## 2026021800

### Added
- Initial release of the Dimensions block plugin.

[2.0]: https://github.com/uaiblaine/moodle-block_dimensions/releases/tag/v2.0
[1.0]: https://github.com/uaiblaine/moodle-block_dimensions/releases/tag/v1.0

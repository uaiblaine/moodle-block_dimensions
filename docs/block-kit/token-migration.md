# Token migration — Dimensions block (Material/Google → Moodle DS)

> **Status: IMPLEMENTED (2026-07-27).** Applied to `styles.css` as a value-only slice: 120 hex
> tokens, 3 gradients and 12 functional colours rewritten, plus 4 contrast corrections and 4 defect
> fixes. `version.php` bumped to `2026072700` so the CSS cache revision moves. No markup, no JS, no
> behaviour changed. The kit's as-is panels now show these values — this file is the record of what
> moved and why, and the list of what deliberately did not.
>
> **Follow-up, same day.** Three of the questions this file left open were then decided and applied:
> the pending-trail marker (open #1), the count badge's missing dark rule (#5) and the inert
> section-header overrides (#6). Sweeping for those turned up four more dark-mode contrast failures
> nobody had logged. All are recorded below under *Dark-mode completion*; the numbered list at the
> end now holds only what is still open.

**Before** = a Material/Google skin (the `#1a73e8` / `#5f6368` / `#f1f3f4` family) with an
Apple-flavoured chrome layer (`#1d1d1f` access pill, a `#004C94 → #297BC4` section-header gradient)
over Boost/Bootstrap 4. **After** = the palette the sibling plugin `local_dimensions` already
shipped, read out of its `styles.css` rather than from any document.

## Why it happened now

`local_dimensions` migrated on 2026-07-20 and `block_dimensions` did not follow. The two render on
the same dashboard and the block's cards link straight into the local plugin's plan and competency
views, so a learner crossed a visible palette seam: the block's accent was Google blue, the
destination page's was Moodle blue. That drift, not a fresh design opinion, is what this closed.

## Migrated — the Google/Material accent family

| Token | Before | After | Role | `styles.css` |
|---|---|---|---|---|
| accent | `#1a73e8` | `#0f6cbf` | search focus, active pill text, fav-pill fill, ghost hover, trail-label hover, and every focus ring | 81, 108, 591, 1094, 1142, 1261, 1267, 1377, 1390, 1553, 1557, 1710, 1781, 1880, 1886, 1898, 1899, 1923, 2066, 2073, 2125 |
| accent hover | `#1765cc` | `#0c5699` | fav-pill hover fill | 1798 |
| accent deep | `#1765cc` | `#0f4d85` | text/border on a subtle-blue fill (mobile toggle on-state, active count badge) | 1409, 1410, 1850 |
| pill platter | `#f1f3f4` | `#e9ecef` | platter and select fill | 1138, 1358 |
| pill text | `#5f6368` | `#6c757d` / `#495057` | see the contrast note below — the value depends on what it sits on | 57, 97, 1089, 1141, 1241, 1376, 1726, 1840 |
| counter fill | `#e8eaed` | `#e9ecef` | count badge | 1839 |
| counter active | `#e8f0fe` | `#cfe2ff` | active count badge, mobile toggle on-state | 1408, 1849 |
| borders | `#e1e3e6`, `#dadce0` | `#dee2e6` | search input, clear button, select, mobile toggle | 66, 1084, 1367, 1408 |
| hover border | `#c6c8ca`, `#d1d5db` | `#ced4da` | select hover, ghost icon circle | 1373, 1890 |
| search glow | `rgba(26,115,232,.15)` | `rgba(15,108,191,.15)` | search focus ring | 83 |
| select chevron | `%235f6368` | `%236c757d` | the SVG data-URI arrow (dark twin `%239aa0a6` → `%23adb5bd`, 1518) | 1359 |

## Migrated — focus rings, consolidated

Four different blues used to indicate the same state. Three are now `#0f6cbf`; the fourth went away
with the rule that drew it:

| Before | Where | Now |
|---|---|---|
| `#005fcc` (+ `rgba(0,95,204,.25)` glow) | card `:focus-within` | `#0f6cbf` + `rgba(15,108,191,.25)` — 230, 239, 437 |
| `#1a73e8` (×9) | title link, pills, star, ghost, trail link, select, clear, retry, search clear | `#0f6cbf` |
| `#0a58ca` | the access pill alone | **deleted, not recoloured** — see *Access pill, hover and focus* |
| `#1765cc` | mobile filter toggle, inside the `575.98px` query | `#0f6cbf` — 1425 |

Dark mode had a fifth (`#66b3ff`); it is now a single `#6ea8fe` (357). `#0f6cbf` on white is 5.36:1,
comfortably over the 3:1 non-text floor, and it matches the ring `local_dimensions` uses everywhere.

## Migrated — both gradients, mechanism untouched

| Surface | Before | After | Line |
|---|---|---|---|
| Plan card | `linear-gradient(135deg, #667eea 0%, #764ba2 100%)` | `linear-gradient(135deg, #0f6cbf 0%, #0a5aa0 100%)` | 452 |
| Competency card | `linear-gradient(135deg, #f5af19 0%, #f12711 50%, #ef4136 100%)` | `linear-gradient(135deg, #ffc107 0%, #fd7e14 50%, #e8590c 100%)` | 258 |
| Section header | `linear-gradient(180deg, #004C94 45%, #297BC4 90%)` | `linear-gradient(180deg, #0f4d85 45%, #0f6cbf 90%)` | 1603 |

The plan-card value is the **exact gradient `local_dimensions` migrated to** (its `styles.css:2569`),
so the two plugins now share one placeholder treatment.

The competency card keeps its **warm three-stop gradient and its halftone overlay** — the
`radial-gradient` dot grid at 5px / `opacity(.5)`, coloured by `--dimension-customtextcolor` falling
back to `#00000082` (269), is deliberately unchanged. Only the three stops moved, onto the
`#fd7e14` orange family that `local_dimensions` keeps as *its* brand accent. That preserves the
warm/cool split which is what tells a learner a competency card from a plan card, while still
landing every literal inside Moodle DS.

## Migrated — status, favourite and chrome

| Token | Before | After | Line |
|---|---|---|---|
| success | `#28a745` | `#198754` | 530, 546, 711 |
| success text | `#1a7431` | `#0f5132` | 575 |
| favourite star | `#ffb100` | `#ffc107` | 1710, 1730, 1752 |
| access pill text | `#1d1d1f` | `#212529` | 653, 941 |
| section-header base | `#1d1d1f` | `#212529` | 1599 |
| clear-filters hover | `#8f1d16` on `#fdecea` | `#842029` on `#f8d7da` | 1099-1101 |
| ghost text | `#666b72` | `#6c757d` | 1917, 1930 |
| ghost circle | `#b0b5bd` | `#adb5bd` | 1903 |
| high-contrast gradient | `#666` | `#6c757d` | 324 |
| print gradient | `#ccc` | `#ced4da` | 372 |

## Migrated — the dark skin

`local_dimensions` ships **no dark mode at all**, so there was no sibling counterpart to copy. The
dark values were mapped onto Bootstrap 5's dark tokens instead:

| Role | Before | After |
|---|---|---|
| recessed surfaces (input, select, platter, ghost) | `#3c4043`, `#292a2d` | `#212529` |
| borders and the indicator pill | `#5f6368` | `#495057` |
| muted text, pill text, icons | `#9aa0a6` | `#adb5bd` |
| input / active-pill / section text | `#e8eaed` | `#dee2e6` |
| accent, focus ring | `#8ab4f8`, `#66b3ff` | `#6ea8fe` |
| accent hover | `#aecbfa` | `#9ec5fe` |
| raised surfaces (select hover, access pill) | `#4a4d51`, `#2c2c2e` | `#343a40` |
| access-pill text | `#f5f5f7` | `#f8f9fa` |
| success | `#4dba6a` | `#75b798` |
| danger (clear hover) | `#ffd7d7` on `#5a1d1d` | `#ea868f` on `#2c0b0e` |
| no-JS notice | `#3c2e00` / `#5c4500` | `#332701` / `#664d03` |
| sticky header scrim | `rgba(32,33,36,.97)` | `rgba(33,37,41,.97)` |
| favourite-button platter | `rgba(50,50,50,.85)` / `rgba(70,70,70,1)` | `rgba(52,58,64,.85)` / `#495057` |

Card surfaces (`#343a40`), borders (`#495057`) and titles (`#f8f9fa`) were already Bootstrap greys
and were left alone.

## Contrast corrections made during the migration

A like-for-like swap would have broken four pairs, because the Moodle greys are lighter than the
Google ones they replaced. These were adjusted rather than migrated blindly:

| Element | Naive result | Corrected to | Ratio |
|---|---|---|---|
| inactive pill text on the `#e9ecef` platter | `#6c757d` — 3.95:1 | `#495057` (1141, 1241) | 6.90:1 |
| select text on its `#e9ecef` fill | `#6c757d` — 3.95:1 | `#495057` (1376) | 6.90:1 |
| count-badge text on `#e9ecef` | `#6c757d` — 3.95:1 | `#495057` (1840) | 6.90:1 |
| search placeholder and mobile-toggle label on `#f8f9fa` | `#6c757d` — 4.45:1 | `#5c636a` (77, 1411) | 5.78:1 |

And one pre-existing failure was fixed in passing: the **tag chip** was white on `#ef4136`, 3.83:1 —
below the 4.5:1 its 12px bold text needs. It is now `#212529` on `#fd7e14`, 6.00:1 (1034-1035,
1040-1041), which is Bootstrap's own convention for a warning-coloured badge. The custom-colour
overrides (`--dimension-custombgcolor` / `--dimension-customtextcolor`) are untouched.

That left one non-text pair short — the pending-trail marker — which the *Dark-mode completion*
section below closes, along with the dark-mode gaps that sweeping for it exposed.

## Defects fixed in passing

- **`styles.css:638`** — `.plan-card-horizontal .card-title` gained `padding-right: 1.75rem`. This
  layout moves the favourite star to the card's top-right (1699-1702) but nothing reserved that
  space in the body, so a long first line ran underneath the 32px star.
- **`styles.css:418`** — the plan-card border was a malformed four-argument
  `rgb(228, 228, 228, 0.44)`; it is now `rgba(0, 0, 0, 0.125)`, matching the competency card.
- **`styles.css:552-557`** — a source comment asserted the pending marker's ring was "≈ 3.1:1"
  against white. It never was; the real figure was 2.07:1. The comment now states the measured
  value and the reasoning behind the ring that replaced it.
- **`styles.css:419`** — the near-white `#FFFEFC` plan-card background, invisible against `#fff` in
  practice and identical to it in dark mode, normalised to `#fff`. Both card types now share one
  surface. The uppercase literals `#FFFEFC`, `#004C94` and `#297BC4` are gone with it.

## Dark-mode completion (applied after the migration)

The pending marker was the trigger: darkening it for contrast would have made pending steps read
heavier than completed ones, so the call was to **darken and thin at the same time** — SC 1.4.11
constrains the ratio, not the stroke, and the extra headroom over 3:1 covers the antialiasing a 1px
ring picks up around a full circle. Outer geometry is unchanged (14px, `border-box`), so nothing
shifts.

Sweeping the file for the same class of problem then found five more, four of them in dark mode
where a light-only rule had simply never been given a counterpart.

| Element | Was | Now | Ratio | Line |
|---|---|---|---|---|
| pending ring, light | 2px `#adb5bd` — **2.07:1** | 1px `#6c757d` | **4.69:1** | 558 |
| pending ring, dark | `#6c757d` on `#343a40` — **2.45:1** | `#adb5bd` | **5.55:1** | 861-865 |
| completed marker, dark | `#198754` — **2.54:1** | `#75b798` | **4.92:1** | 869-872 |
| completed connectors, dark | `#198754` — **2.54:1** | `#75b798` | **4.92:1** | 874-879 |
| trail-label hover, dark | `#0f6cbf` — **2.15:1** | `#6ea8fe` | **4.76:1** | 882-885 |
| select focus ring, dark | `#0f6cbf` on `#212529` — **2.88:1** | `#6ea8fe` | **6.39:1** | 1517-1521 |
| count badge, dark | **no rule** — a light `#e9ecef` island on the `#212529` platter | `#495057` fill, `#dee2e6` text | **6.28:1** | 1525-1529 |
| mobile filter toggle, dark | **no rule** — a light `#f8f9fa` disc on the dark sticky header | `#212529` / `#495057` / `#adb5bd`; on-state and focus `#6ea8fe` | **7.43:1** rest, **6.39:1** on | 1422-1441 |

The toggle's dark rules live **inside** the `@media (max-width: 575.98px)` block, because the control
only exists below the breakpoint.

**Section header, dark and print (1628-1641).** Both overrides were inert:
`-webkit-text-fill-color: transparent` on the base rule (1617) beats `color`, so a rule that only set
`color` painted nothing. Both now set `background-image: none` and
`-webkit-text-fill-color: currentcolor` first, so the dark `#dee2e6` and the print `#000` apply.

**Focus rings (2193-2247).** The migration collapsed every focus indicator onto `#0f6cbf`, but only
three had ever been restated for dark: the competency card, the select and the mobile toggle. The
other eleven stayed `#0f6cbf` on a dark surface — **2.15:1** on the `#343a40` card, **2.88:1** on the
`#212529` page — both under the 3:1 floor for a focus indicator, which the kit's own body text had
been recording as fact all along without anyone connecting it to the summary claim. One consolidated
block now flips all of them to `#6ea8fe` (4.76:1 / 6.39:1), including the plan card's glow and the
filter pill's inset ring. Verified in a browser rather than by grep: every one of the thirteen
indicators resolves to `#6ea8fe` under `body.dark`.

With these in, **no text or non-text pair the block controls fails WCAG 2.1 AA in either skin**, with
one documented exception under `prefers-contrast: high` — see the open list. The *active* count badge
inside a checked pill deliberately keeps `#cfe2ff` / `#0f4d85` in both skins (6.60:1 either way), so
it never needed a dark variant.

**Access pill, hover and focus (2131-2185).** Three rules styled the pill **as a descendant of the
card link** — the hover lift (`0 4px 14px/.22` + `translateY(-1px)`) and its own `:focus-visible`
ring. It never is one: both templates put the pill inside the image wrapper and the link inside the
card body (`plan_card.mustache:93` vs `:99`, `competency_card.mustache:84` vs `:90`), so they are
sibling subtrees. The lift had never fired for a single user, and the article-scoped block that was
meant to replace them only set `opacity: 1` — itself a no-op, since nothing sets the pill's opacity
below 1.

All three were deleted and replaced by one article-scoped block: `.plan-card:hover` /
`.competency-card:hover` / `:focus-within` (plus the two horizontal variants) now carry the lift,
because `stretched-link` makes both states bubble to the article. Dark deepens the hover shadow to
`rgba(0,0,0,.5)` — the dark rest state is already `.35`, so the light value would have read flatter
than rest — and `prefers-reduced-motion: reduce` drops the transform like every other transform in
the file.

**Hover and keyboard focus deliberately share one treatment.** The pill did not get its own ring
back: the article already draws a 3px ring plus a 4px glow around the whole card, so a second ring on
a decorative, `aria-hidden`, `pointer-events: none` element would put two rings on screen for one
focus event. Verified in a browser — all three pills resolve the lift on `:hover` and
`:focus-within`, in both skins.

## Open — needs a decision, deliberately not applied

1. **`prefers-contrast: high` assumes a light background.** That block paints `#000` borders and
   outlines (311-321, 1558-1571 and the per-component high-contrast rules) with no dark counterpart,
   so a user on dark + high-contrast gets black on `#343a40`. The rest of the file now flips cleanly
   with the skin; this one corner does not.
2. **The default tag chip disappears into the competency card's own gradient.** Chips sit at the
   image band's bottom-left (1010-1018), which on a `135deg` gradient lands on roughly the middle
   stop — and the middle stop is now `#fd7e14`, exactly the chip's own default fill (1034). Chip
   against backdrop is **1.00:1**; only the `0 1px 3px rgba(0,0,0,.2)` shadow separates them.
   This is inherited, not introduced — the old pairing was `#ef4136` on `#f12711`, 1.09:1, equally
   invisible — but the migration made the collision exact. Note the *text* is unaffected and still
   passes (`#212529` on `#fd7e14`, 6.00:1); this is legibility of the chip's edge, not of its label.
   Three ways out, all design calls: give the chip a neutral fill that reads on any card colour,
   give it a hairline border, or accept it on the grounds that admins set
   `--dimension-custombgcolor` in practice and the default is only a fallback.
3. **Input borders are decorative, not identifying.** `#dee2e6` on white is 1.30:1. This is only a
   WCAG 1.4.11 failure if the border is the sole means of identifying the control; the search field
   and select both carry a fill distinct from the page, so it arguably is not. Unchanged from before
   the migration; worth a deliberate ruling rather than a silent pass.
4. **The block ships a dark skin the sibling does not.** `local_dimensions` has no `.theme-dark`
   rules at all. The block's dark mode is now complete and internally coherent on Bootstrap 5 dark tokens, but it
   has no counterpart to stay in sync with — so it will drift again unless one plugin adopts the
   other's position.
5. **No custom-property layer.** Every value is still a literal. The filter platter already
   demonstrates the better pattern — `styles.css:1123-1143` defines a dozen `--dims-tabs-*`
   properties and dark mode overrides only those (1493-1500). Lifting the rest of the file to
   `--bk-*` (or Boost's `--primary` / `--bs-*`) would make the next theme change one block instead
   of 120 sites. `local_dimensions` itself only does this in 16 of its 90 primary uses, so this is a
   shared improvement rather than a block-only gap.

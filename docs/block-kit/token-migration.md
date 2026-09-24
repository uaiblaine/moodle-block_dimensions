# Token migrations — Dimensions block

Two migrations, recorded in order. **2026-07-27:** Material/Google → Moodle DS, a value-for-value
swap of 120 literals. **2026-09-05:** literals → a 34-token contract shared byte-for-byte
with `local_dimensions`, declared on `:root` then and on `body` since the 2026-09-23 re-baseline,
which is where the second half of this file starts. Read the first as
history: almost every value it lists has since been replaced by a token read, and the section at the
end says which of its open questions that closed.

# First migration — Material/Google → Moodle DS (2026-07-27)

> **Status: IMPLEMENTED (2026-07-27), then SUPERSEDED (2026-09-05).** Every value below still
> describes a real change that shipped, but almost none of them is still a literal in `styles.css`:
> the second migration replaced them with token reads. The sections most affected are flagged where
> they start. Read this half for provenance and for the reasoning; read the second half for what the
> file contains today.
>
> Applied to `styles.css` as a value-only slice: 120 hex
> tokens, 3 gradients and 12 functional colours rewritten, plus 4 contrast corrections and 4 defect
> fixes. `version.php` bumped to `2026072700` so the CSS cache revision moves. No markup, no JS, no
> behaviour changed. The kit's as-is panels showed these values until 2026-09-05 — this half is the
> record of what moved and why, and of what deliberately did not.
>
> **Follow-up, same day.** Three of the questions this file left open were then decided and applied:
> the pending-trail marker (open #1), the count badge's missing dark rule (#5) and the inert
> section-header overrides (#6). Sweeping for those turned up four more dark-mode contrast failures
> nobody had logged. All are recorded below under *Dark-mode completion*.

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

> **Superseded 2026-09-05.** The consolidation was right and the colour was wrong. Every indicator
> below is now `outline: 2px solid var(--block-dimensions-focus-ring)` at `outline-offset: 2px`, an
> ink extreme rather than a blue — see *The focus indicator, converged* in the second half. The
> `#0f6cbf` this section lands on measured **2.15:1** on the dark card, which is under the 3:1 floor,
> and eleven of the thirteen indicators kept it there.

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

> **Superseded 2026-09-05.** Both gradients are built from tone tokens now, so their stops flip with
> the host; the halftone and the admin-colour overrides are unchanged, as they were then.

| Surface | Before | After | Line |
|---|---|---|---|
| Plan card | `linear-gradient(135deg, #667eea 0%, #764ba2 100%)` | `linear-gradient(135deg, #0f6cbf 0%, #0a5aa0 100%)` | 452 |
| Competency card | `linear-gradient(135deg, #f5af19 0%, #f12711 50%, #ef4136 100%)` | `linear-gradient(135deg, #ffc107 0%, #fd7e14 50%, #e8590c 100%)` | 258 |
| Section header | `linear-gradient(180deg, #004C94 45%, #297BC4 90%)` | `linear-gradient(180deg, #0f4d85 45%, #0f6cbf 90%)` | 1603 |

The plan-card value is the **exact gradient `local_dimensions` migrated to**, so the two plugins now
share one placeholder treatment. (The line number this sentence used to carry is dropped rather than
re-derived: it pointed into the *sibling* plugin's stylesheet, which has been rewritten since, and
this section records the 2026-07-27 migration rather than mapping either file as it stands today.)

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

> **Superseded 2026-09-05.** The premise of this section — that `local_dimensions` shipped no dark
> mode, so the block had no sibling counterpart to copy — stopped being true, and the whole table
> below stopped being shipped code. Both plugins now declare the same 34-token contract with
> byte-identical suffixes, and the `.theme-dark` / `body.dark` rules that carried these values were
> deleted as dead code. Kept as the record of where the values came from.

At the time, `local_dimensions` shipped no dark mode at all, so there was no sibling counterpart to
copy. The dark values were mapped onto Bootstrap 5's dark tokens instead:

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

> **Superseded in part.** All four fixes still hold, but three of them no longer take the form
> recorded here: the 2026-09-05 migration replaced the border and background literals with token
> reads, and the pending marker's source comment has since dropped its measurement history, which
> this file keeps. Each entry says what shipped on 2026-07-27, then what the cited rule holds today.

- **`styles.css:942`** — `.plan-card-horizontal .card-title` gained `padding-right: 1.75rem`. This
  layout moves the favourite star to the card's top-right (1868-1872) but nothing reserved that
  space in the body, so a long first line ran underneath the 32px star. Unchanged since; the
  comment beside the declaration (948-952) gives the arithmetic.
- **`styles.css:533`** — the plan-card border was a malformed four-argument
  `rgb(228, 228, 228, 0.44)`; the fix made it `rgba(0, 0, 0, 0.125)`, the competency card's
  border. That literal is gone: both cards now read `border: 1px solid var(--block-dimensions-line)`
  (541 here, 324 on `.competency-card`) — core's `--bs-border-color`, `#dee2e6` on 4.5 — so they
  still match.
- **`styles.css:865-876`** — a source comment asserted the pending marker's ring was "≈ 3.1:1"
  against white. It never was; the real figure was 2.07:1, and the fix rewrote the comment to say so
  and to give the reasoning behind the 1px `#6c757d` ring that replaced it (4.69:1). The comment on
  that rule (868-872) now states only the current contract — `ink-muted` on the card surface, and
  why the stroke stays 1px — and the measurements live here, under *Dark-mode completion* and *The
  eight dark contrast repairs, retired without regression*.
- **`styles.css:533`** — the near-white `#FFFEFC` plan-card background, invisible against `#fff` in
  practice and identical to it in dark mode, was normalised to `#fff` so both card types shared one
  surface; the uppercase literals `#FFFEFC`, `#004C94` and `#297BC4` went with it. Both cards now
  read `background-color: var(--block-dimensions-surface)` (542 here, 325 on `.competency-card`) —
  core's `--bs-body-bg`, Boost's `--white` on 4.5 — so they still share one surface.

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
block flipped all of them to `#6ea8fe` (4.76:1 / 6.39:1), including the plan card's glow and the
filter pill's inset ring. Verified in a browser rather than by grep at the time: every one of the
thirteen indicators resolved to `#6ea8fe` under `body.dark`.

**And that verification is the cautionary part of this file.** It was true of the *rendered page in a
browser with `body.dark` set by hand*, and it was taken as evidence that dark mode worked. It was
not: **nothing sets `body.dark`.** No Moodle version in the plugin's supported range, and no theme in
this fleet, has ever emitted that class or `.theme-dark`. Thirteen indicators verified in a state no
user could reach. The 2026-09-05 migration below deleted all 154 of those rules.

With these in, **no text or non-text pair the block controls fails WCAG 2.1 AA in either skin**, with
one documented exception under `prefers-contrast: high` — see the open list. The *active* count badge
inside a checked pill deliberately keeps `#cfe2ff` / `#0f4d85` in both skins (6.60:1 either way), so
it never needed a dark variant.

**Access pill, hover and focus (2131-2185).** Three rules styled the pill **as a descendant of the
card link** — the hover lift (`0 4px 14px/.22` + `translateY(-1px)`) and its own `:focus-visible`
ring. It never is one: both templates put the pill inside the image wrapper and the link inside the
card body (`plan_card.mustache:109` vs `:115`, `competency_card.mustache:91` vs `:97`), so they are
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

## The 2026-07-27 open list, and what became of each

Four of the five are closed by the 2026-09-05 migration below. Struck-through headings are closed;
the one that is still open says so.

1. ~~**`prefers-contrast: high` assumes a light background.**~~ **Closed 2026-09-05.** Every remedy
   in those blocks that names an *extreme* is now `var(--block-dimensions-ink-strong)` or
   `var(--block-dimensions-focus-ring)`, both of which chain `--bs-emphasis-color` and therefore flip:
   `#000` on the light page, `#fff` on the dark one, `#343a40` on Moodle 4.5. The card border and
   title, the select's border, the active filter pill's border and the indicator's outline all
   follow the page now. `currentColor` covers the rest — the access pill, tags, star, clear button
   and ghost card — and needs no token, because it is already whatever ink the element inherited.
   **One literal survives on purpose**, and it is the one that names a *middle* rather than an
   extreme: the competency gradient still flattens to `#6c757d`. A flat fill in the image slot has to
   separate from the card face in both modes at once, so it can be neither extreme — `#6c757d` is
   4.69:1 on the light card face and 3.45:1 on the dark one, and a token that flipped would turn the
   image area light on a dark page and invert the card. That literal is on the token contract's
   exemption list, and the contract fails the build if the list ever holds an entry matching no live
   rule, so the exemption cannot rot into a blanket.
   **A second, different block was added at the same time and should not be confused with this one:**
   `@media (forced-colors: active)`. Windows High Contrast Mode substitutes the browser's palette for
   every `background-color`, so the white sliding indicator and the filled active pill both flatten
   to the same Canvas as their neighbours and the selected filter stops being distinguishable
   (WCAG 1.4.1). An `outline` is the fix because outline *is* remapped there, while a background is
   overwritten and a `box-shadow` is not drawn at all — which is also why no focus indicator in the
   file is a `box-shadow` any more.
2. ~~**The default tag chip disappears into the competency card's own gradient.**~~ **Closed
   2026-09-05, by the first of the three ways out.** The chip's unconfigured fill is now
   `var(--dimension-custombgcolor, var(--block-dimensions-surface-inset))` over
   `var(--dimension-customtextcolor, var(--block-dimensions-ink))` — a neutral that reads on any card
   colour, in either mode, and separates from every stop of both gradients. The 1.00:1 collision is
   gone. The *configured* chip is untouched, and its contrast is still the site admin's to own,
   which is the correct division: the admin's colour is instance data, not a design token, and the
   mode layer is forbidden from declaring it.
3. **Input borders are decorative, not identifying.** `#dee2e6` on white is 1.30:1. This is only a
   WCAG 1.4.11 failure if the border is the sole means of identifying the control; the search field
   and select both carry a fill distinct from the page, so it arguably is not. Unchanged from before
   the migration; worth a deliberate ruling rather than a silent pass.
4. ~~**The block ships a dark skin the sibling does not.**~~ **Closed 2026-09-05, by deleting the
   skin.** The premise was right and the framing was wrong: the drift risk was real, but the skin was
   not something to keep in sync — it was 154 rules keyed off selectors nothing emits. Both plugins
   now declare the same 34 token suffixes, byte-identical, and a test in each compares its own block
   against the sibling's with the frankenstyle prefix rewritten to a sentinel.
5. ~~**No custom-property layer.**~~ **Closed 2026-09-05, and it turned out to be the fix for
   questions 1 and 4 as well.** This was logged as an ergonomics improvement — "the next theme change
   is one block instead of 120 sites" — and that undersold it. Lifting the file onto
   `var(--bs-NEW, var(--BS4-OLD, #literal))` chains did not merely centralise the values; it made 31
   of the 34 tokens *core's* values, which is what made the whole hand-written dark layer
   unnecessary, made the high-contrast block flip, and gave the two plugins a parity contract a test
   can compare. Both plugins did it together, and both did all 34, not the 16-of-90 the note
   anticipated.

---

# Second migration — literals to a 34-token contract (2026-09-05)

> **Status: IMPLEMENTED (2026-09-05).** Applied to `styles.css` as a value-and-structure slice:
> every colour literal outside three documented exemptions replaced by a token read, the
> `.theme-dark` / `body.dark` layer deleted, the focus indicator converged on one shape, the
> container-query layer removed, and the plugin's own `.stylelintrc.json` deleted. `version.php`
> bumped so the CSS cache revision moves. 17 PHPUnit methods and 4 Behat scenarios were added with
> it, every one mutation-checked. The kit's panels were re-baselined the same day.
>
> **Moved to `body` on 2026-09-23.** The contract was declared on `:root`, and the activation rule
> anchored there, when this section was written. Both now sit on `body`; the two passages below
> that argue for `:root` are marked where they stand, and the last section of this file says why.

**Before** = the Moodle DS palette the first migration produced: correct values, but ~120 literals,
each needing a hand-written dark twin. **After** = 34 custom properties declared once on bare
`:root` (on `body` since 2026-09-23), with the suffix set byte-identical to `local_dimensions`'
`--local-dimensions-*` set.

## What the contract is

Thirty of the 34 are three-rung chains, `var(--bs-NEW, var(--BS4-OLD, #literal))`. Moodle 4.5
declares **zero** `--bs-*` custom properties and Moodle 5.2 declares **zero** BS4 legacy names, so
one chain is correct on every supported branch with no branch test anywhere. On 4.5 a chain lands on
its *middle* rung wherever Boost declares the legacy name — `--white`, `--light`, `--gray-dark`,
`--primary` — and on the terminal literal otherwise. Every literal in the block was chosen to equal
the middle rung it stands behind, verified value by value against the compiled Boost sheets of the
running m405 and m502 stacks on 2026-09-05.

> **Superseded 2026-09-23 on the element, not on the reasoning.** The block is declared on `body`
> now. `body` is still the ancestor of every node the plugin paints, anything appended to
> `document.body` included, and it is also where theme_moove writes `data-bs-theme`; see the last
> section.

`:root` and not a plugin class, deliberately. Custom properties substitute at computed-value time on
the element carrying the declaration; `:root` is the ancestor of every node in the document, so one
rule covers every root the plugin can paint, including anything core relocates to `document.body`.
That completeness is not cosmetic: **an unresolved `var()` does not fall back to its literal** — the
whole declaration is invalid at computed-value time, so a background set from an undeclared token is
not the default background, it is *no* background.

## Why it made the dark skin unnecessary

Because 31 of the 34 tokens resolve to core's own `--bs-*` values, and Moodle 5.1 and 5.2 already
compile a complete `[data-bs-theme="dark"]` token block, those 31 are dark-correct **with no dark
rule at all**. The plugin's card face *is* `--bs-body-bg`, which is also the page's own background,
so the two cannot disagree.

Only **three** tokens carry a plugin-authored dark value — `shadow`, `scrim` and `favourite` — and
they are the entire contents of the one activation rule:

```css
:root[data-bs-theme="dark"] {
    --block-dimensions-shadow: rgb(0 0 0 / 55%);
    --block-dimensions-scrim: rgb(29 33 37 / 72%);
    --block-dimensions-favourite: #fd7e14;
}
```

That bound is a second, independent guarantee: the worst a wrongly-firing activation block can do is
deepen a shadow, darken a veil and brighten a star. It cannot paint a dark surface on a light page.

> **Superseded 2026-09-23.** The rule is now
> `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`, the attribute on `body` or on `html`,
> at (0,1,1) against the token block's (0,0,1). The argument below against a bare selector holds
> unchanged: `body` as the subject still keeps every scope below it out.

The rule is **anchored at `:root` on purpose**. A bare `[data-bs-theme="dark"]` matches through any
ancestor at any depth, and CSS descendant combinators have no nearest-ancestor-wins rule. That is not
hypothetical: `theme_boost_union_fundaseg` sets `data-bs-theme="dark"` on the navbar element itself,
and `theme_boost_union` then re-pins `data-bs-theme="light"` by hand on five nested templates to stop
the dark scope leaking into its own submenus. A bare selector would ignore those re-pins. `:root`
restricts the match to the `html` element and forecloses the whole class at zero cost — and it is
(0,2,0) against the token block's (0,1,0), so it wins on specificity rather than on source order.

## What was deleted, and why it was dead

**154 rules — 77 keyed off `.theme-dark` and 77 off `body.dark`.** Nothing in Moodle 4.5, 5.0, 5.1,
5.2 or 5.3-dev, and nothing in `theme_boost_union` or `theme_boost_union_fundaseg`, has ever emitted
either selector. They could not fire on any site the plugin supports.

The signal that *does* exist is Bootstrap 5.3's own `data-bs-theme`, and Moodle **5.3** core writes it
on the `<html>` element from `theme_boost\colour_mode` via `before_html_attributes`, with a head
script that resolves `auto` through `matchMedia` and writes the result back. It is gated behind
`theme_boost/enablecolourmodes` and is **off by default**; core's own comment gives the reason, that
a plugin which has not been checked in dark mode can still draw its pages in light colours. It does
not exist on 4.5, 5.0, 5.1 or 5.2 at all — which is why the Behat scenarios set the attribute
themselves rather than asking a theme for it.

Also gone: the last `!important` (Moodle's stylelint forbids the keyword; the admin fill moved off
the inline style attribute onto a `.has-custom-bg` class reading a custom property, so the print and
high-contrast overrides win at equal specificity instead), every inline SVG data URI (stylelint's
`function-url-scheme-disallowed-list`; the select chevron is two `currentcolor` gradients now and the
dashed trail stubs are `repeating-linear-gradient` on the `line` token, both of which follow the host
for free where a re-encoded SVG needed a hand-written second copy), and the container-query layer.

## The one live `@media (prefers-color-scheme: dark)` block, and the one that is inert

The block that *was* live has been removed: it flipped the WCAG contrast panel from the OS preference
while the rest of the Moodle page stayed light, which is exactly the defect the whole design exists
to prevent.

A `@media (prefers-color-scheme: dark)` block is still present, carrying the same three decorative
tokens, and it is **deliberately inert**: every selector in it is gated on
`[data-dimensions-media-optin]`, an attribute nothing in either plugin ever writes — no PHP, no AMD
module, no Mustache template, no Behat step, no test. `colour_mode::MEDIA_OPTIN_ATTRIBUTE` names it
as a constant and nothing assigns it.

It is inert because **the OS preference is the wrong signal on its own**. Core reads
`prefers-color-scheme` in a head script and writes the *result* into `data-bs-theme`, and only when
the user's stored mode is `auto`; it treats the OS preference as an **input** to the attribute, never
as an independent trigger. Firing on the media query directly would override an explicit user choice
with an OS setting. Switching it on is one edit — delete `[data-dimensions-media-optin]` from the
selector — and retiring it is the preferred outcome once the supported minimum reaches 503, because
core resolves `auto` itself from 5.3 on.

## The focus indicator, converged

One shape, everywhere, in both plugins of the family:

```css
outline: 2px solid var(--block-dimensions-focus-ring);
outline-offset: 2px;
```

Two exceptions, each with its reason beside it in the source, and both keep a **real** outline: the
filter tab and the tabs indicator draw at `outline-offset: -2px` because their platter is
`overflow: hidden` and would clip an outset ring.

Three things changed and each has a measurement behind it. The card's ring came down from 3px to 2px,
because two ring widths on one page read as a drawing error rather than as two meanings. The 4px
brand glow is gone, and so is the filter pill's inset ring, because **a `box-shadow` is not rendered
at all under `forced-colors: active`** — a control whose only focus signal was one had no indicator
in Windows High Contrast Mode. And the colour is `--bs-emphasis-color` rather than the brand:
21.0:1 light, 16.2:1 dark, 11.5:1 on 4.5, against a site primary that measures 2.33:1 as a ring on
the dark card on this fleet's own theme. It deliberately does **not** chain
`--bs-focus-ring-color`, which core fails to flip — 1.02:1 on the dark page.

The card link's two `outline: none` rules were deleted as well: an author `outline: none` is not
restored by the browser under forced colours, so a rule that suppresses the outline and puts nothing
in its place leaves the control with no focus indicator at all.

## The eight dark contrast repairs, retired without regression

Every repair in *Dark-mode completion* above existed because the light value beside it was a literal.
All six roles are single declarations now, and each resolves correctly in both modes with nothing to
keep in step:

| 2026-07-27 repair | 2026-09-05 replacement |
|---|---|
| pending ring, light `#6c757d` + dark `#adb5bd` | one `ink-muted` read |
| completed marker and connectors, dark `#75b798` | one `success-ink` read |
| trail-label hover, dark `#6ea8fe` | one `accent` read |
| select focus ring, dark `#6ea8fe` | one `focus-ring` read |
| count badge, dark `#495057` / `#dee2e6` | `surface` under `ink-muted` |
| mobile filter toggle, dark block | `surface-alt` / `line` / `ink-muted`, `brand-tint` / `brand-ink` on |
| card `:focus-within`, dark `#6ea8fe` + glow | one `focus-ring` read, no glow |
| access-pill lift, dark `rgba(0,0,0,.5)` | one `shadow` read |

The count badge picked up a real improvement on the way. Its rest fill had been `#e9ecef`, the same
value as the platter it sits on, so the chip had no shape at all and only its bold text showed; it is
`surface` now, which gives it a boundary and lifts its ink from 5.99:1 to 7.10:1 in light. That
boundary was a faint one, 1.19:1 against the platter in light; since 2026-09-24 a 1px `ink-muted`
border edges the badge (see the last section).

## What this migration cost

Recorded because a re-baseline that only lists wins is not a record.

- **The horizontal plan card no longer stacks on a narrow block column at a wide viewport.** It was
  declared twice, `@media (max-width: 575.98px)` and `@container dims-card-cell (max-width: 360px)`,
  and the container copy — the one that mattered in a Moodle side region — is gone. Moodle's
  stylelint reports `@container` as an unknown at-rule and `container-type` as an unknown property.
- **The card list's column cap changed mechanism.**
  `repeat(auto-fill, minmax(clamp(240px, 30%, 500px), 1fr))` became `flex: 1 1 30%; min-width: 15rem`
  on each item. `clamp()` inside a length-valued property is rejected by `csstree/validator`. A flex
  basis is still a percentage *of the container*, so the column count still answers to the block
  column rather than the viewport; what is lost is restyling a card by the width of its own cell.
- Both are consequences of one deletion: **`.stylelintrc.json` had no `extends`**, so it was silently
  *replacing* Moodle's ~90-rule config rather than extending it, and was hiding 46 errors and 3
  warnings. Removing the file is what surfaced them.

## Still open

3. **Input borders are decorative, not identifying.** `line` on `surface` is 1.30:1 in light. This is
   only a WCAG 1.4.11 failure if the border is the sole means of identifying the control; the search
   field and the select both carry a fill distinct from the page, so it arguably is not. Unchanged
   through both migrations, and worth a deliberate ruling rather than a silent pass. Note the value
   is core's own `--bs-border-color` now, so a ruling here is a ruling about core's hairline as much
   as about the plugin's.

# Re-baseline — the move to `body`, raised contrast, the track floor and the favourite disc (2026-09-23)

No token value changed: the contract is the same 34 declarations, and the activation rule assigns
the same three tokens. What moved is the element both are declared on, where the tokens are applied,
and four rules that never took effect.

- **The token block and the activation rule moved from `:root` to `body`.** A custom property is
  substituted on the element that declares it and inherits as the substituted value, and Bootstrap
  redefines the `--bs-*` set on whichever element carries `data-bs-theme`. Moodle 5.3 writes that
  attribute on `html`; theme_moove writes it on `body`. Tokens declared on `:root` would keep html's
  light values under theme_moove, while tokens declared on `body` see both. The activation rule
  became `body[data-bs-theme="dark"], [data-bs-theme="dark"] body` — (0,1,1) against the token
  block's (0,0,1), so it wins on specificity — and still cannot be reached from a scope below
  `body`, such as a dark navbar, because inheritance runs downwards only. The inert media block's
  gate moved with it: `[data-dimensions-media-optin]` is required on `body` now.
  `colour_tokens_test::test_activation_selectors_have_body_as_subject` pins the subject.

- **The raised-contrast blocks never matched.** All seven asked for `prefers-contrast: high`. Media
  Queries Level 5 defines `no-preference`, `more`, `less` and `custom`; a browser reads any other
  value as false, so every remedy recorded above under that heading — the `ink-strong` borders, the
  `#6c757d` flat gradient, the active tab's border — had never applied. They ask for `more` now, and
  `card_layout_test::test_preference_queries_use_defined_values` fails on an undefined value.
  `local_dimensions` carries the same query four times.
- **Three preference overrides lost on specificity.** The raised-contrast tab border and the
  reduced-motion tab transition were written as `.block_dimensions .dims-filter-tab` (0,2,0) against
  a base rule under `.block-dimensions-content` (0,3,0), so the tab kept `border: none` and its
  transition; the reduced-motion paddle rule lost to the hidden paddle's own transition, and the
  reduced-motion trail rule lost to `.trail-item.clickable:hover .trail-marker`. Each now repeats the
  depth of the rule it overrides, and `card_layout_test::test_preference_overrides_are_not_outranked`
  compares every preference override with the base rules for the same element and state.
- **The track floor is capped at the block's width.** Every grid reads
  `minmax(var(--dims-card-track-min), 1fr)`, and the floor is `min(19rem, 100%)`, `min(26rem, 100%)`
  for horizontal plan cards and `min(360px, 100%)` for competencies. The competency grid's bare
  `360px` overflowed any block narrower than that — Boost's 315px drawer, a phone — and the other two
  did the same below their own widths. The floor rides a custom property because the stylelint
  config rejects `min()` inside `minmax()`, and does not validate a declaration that reads `var()`.
  In a wide region the column count is what it was.
- **The favourite star sits on an opaque disc.** The disc was the translucent `scrim`, so 28% of the
  card art showed through and moved the star's ground: over black art the light star measured
  1.80:1, over white art the dark one 2.50:1, both under the 3:1 of WCAG 1.4.11. It is `surface`
  now (the filled star 3.58:1 light, 6.30:1 dark) and steps to `surface-alt` on hover (3.40:1 and
  5.33:1). `scrim` stays in the contract for the mobile sticky header.
  `colour_tokens_test::test_favourite_star_sits_on_an_opaque_ground` pins the opaque ground.
- **The plan card had none of the competency card's raised-contrast and print treatment.** Under
  `prefers-contrast: more` the competency card drew a 2px `ink-strong` border and an `ink-strong`
  title while the plan card kept its 1px `line` border, 1.30:1 against the light page; in print the
  competency card dropped its shadow and took a 1px `#000` border and `break-inside: avoid`, and the
  plan card had no print rule at all. Both blocks now name both cards and both gradients (`#6c757d`
  under raised contrast, `#ced4da` in print, custom fills included), and they sit after both cards'
  own rules, since they win on source order at those rules' specificity.
  `card_layout_test::test_card_shells_share_their_preference_treatment` pins the parity.
- **A fourth override lost, this time to a state rule.** Under `prefers-contrast: more` the access
  pill trades its shadow for a 2px border, but the article-scoped lift (0,4,0) outranks that swap
  (0,2,0), so the pill of a hovered or focused card got its shadow back. An override repeating the
  lift's six selectors now follows it, and
  `card_layout_test::test_switched_off_properties_stay_off_in_every_state` checks that a property a
  preference switches off stays off in every state rule for the same element.
- **A checked status pill's count badge had no shape.** The active-badge rule named only the
  favourites and show-all pills, so a checked status pill kept the resting `surface` badge on the
  indicator, which paints `surface` too. The rule is keyed on the checked tab now,
  `.dims-filter-tab.active .dims-filter-count` (`brand-ink` on `brand-tint`), and
  `card_layout_test::test_checked_pill_badge_stands_off_the_indicator` checks the winning badge fill
  against the indicator for every pill class `filters.js` draws.
- **That badge then had a shape, but a faint one.** `brand-tint` stands off the indicator's
  `surface` at 1.33:1 in light and 1.13:1 in dark, so the checked badge now carries a 1px `brand-ink`
  border, 14.41:1 and 6.33:1 against it, with its padding cut from `0 6px` to `0 5px` so the pill
  kept its width (since 2026-09-24 the resting badge is edged too and the checked rule takes its
  padding from it; see the next section). `brand-edge`, core's own border for the pairing, was measured and rejected at
  1.83:1 and 1.55:1. `colour_tokens_test` gained `brand-ink` on `surface` as a 3:1 row of its contrast
  pairs, and `test_checked_pill_badge_edge_is_pinned` fails if the badge's border names a token that
  row does not hold; `card_layout_test` checks the border reaches every checked pill as well.

Every `file:line` citation in the kit — `styles.css`, the templates, the AMD modules, the PHP
classes and the lang file — was re-anchored to the current files in the same pass.
Citations that already pointed at the wrong rule were corrected where found: `FLT-BAR` named the
clear button's lines rather than `.dims-filters-bar`, and a handful written against the original
stylesheet of 2026-07-27 (the competency card hover, the image heights, the trail list reset, the
ghost card hover) had not moved with it. The *Defects fixed in passing* entries had the opposite
problem: their line numbers followed the file while their prose still described the 2026-07-27
literals, so each now also says what its cited rule holds today.

# Re-baseline — the resting count badge and the card titles (2026-09-24)

No token value changed, and no token was added.

- **The resting count badge had the checked badge's faint outline.** An unchecked pill paints no
  fill, so its badge sits on the `surface-inset` platter, and its `surface` fill stands off that at
  1.19:1 in light and on 4.5 and 1.41:1 in dark. It now carries a 1px `ink-muted` border, the same
  token as its number, which clears 3:1 against the platter in every resolution: 5.99:1, 5.38:1 and
  6.90:1 as drawn over the badge's own fill. (The unchecked pill still carried an `opacity: 0.8` at
  the time, which multiplied into this border and its own label; that opacity is gone as of the next
  re-baseline below, so the badge and the label it sits beside now draw at the numbers above, full
  strength.) The resting padding went from `0 6px` to `0 5px`, and the checked rule no
  longer sets one of its own: both badges are as wide as the unbordered badge was, so neither state
  moves a pill. `ink-muted` on `surface-inset` was already a 4.5:1 row of the contrast pairs;
  `colour_tokens_test::test_resting_pill_badge_edge_is_pinned` fails when the resting badge's border
  names a token no row holds against the platter, and
  `card_layout_test::test_resting_pill_badge_stands_off_the_platter` checks, for every pill class
  `filters.js` draws, that the winning resting border draws a line in a token other than the
  platter's fill.
- **The card titles take their weight from the stylesheet.** Both card templates set their titles
  bold with the Bootstrap 4 class `font-weight-bold`, which 5.x resolves only through
  `bs4-compat.scss`, inside the deprecated-styles mixin (a red outline under behat-site and
  themedesignermode), and which Moodle 6.0 removes; its Bootstrap 5 spelling, `fw-bold`, does not
  exist on 4.5. The `.card-title` rules of both cards declare `font-weight: 700` instead
  (`styles.css:431-446`, `:593-597`), which renders the same on every branch, and
  `bootstrap_compat_test` now rejects the `font-weight-*` family and `font-italic` with the other
  Bootstrap 4 names.

The kit's `file:line` citations were re-anchored in the same pass: the title rules moved every
`styles.css` line after them by four or five, and the badge rules and the status-filter code in
`filters.js` and `state.js` moved the lines around them.

# Re-baseline — the unchecked pill loses its opacity (2026-09-24)

No token value changed. **`.dims-filter-tab` no longer carries `opacity: 0.8` at rest or
`opacity: 1` on hover/active.** An opacity multiplies into everything the pill paints, including its
own label and the count badge sitting inside it, and 0.8 was enough to fail AA for text this size:
`ink-muted` on the platter measured 6.46:1 / 5.76:1 / 6.90:1 (light / dark / 4.5) as declared, but
4.07:1 / 4.30:1 / 4.28:1 as drawn — under the 4.5:1 floor on every branch. `colour_tokens_test`'s
`PAIRS` table held the declared numbers only; nothing measured the opacity-drawn ones, so the defect
had no gate.

The fix removes the opacity rather than raising it: the checked/unchecked distinction was never
carried by fade alone (the checked pill already sits on the sliding indicator and takes the accent
colour), so nothing needs it. `:hover` no longer restates `opacity: 1` either — it is scoped to
`:not(.active)` and steps the label from `ink-muted` to `ink` (13.66:1 light, 8.84:1 dark on the
platter) instead, which is the effect the fade was standing in for. `colour_tokens_test` now reads
the label and the count badge against the platter with **any** opacity applied to the rule or an
ancestor, so a future fade regresses the same way this one did, loudly.

This also retires the discount every "through the unchecked pill's 0.8 opacity" figure in this file,
`tokens.html` and `maps/block.md` carried for the resting count badge's border and label: those
numbers were always the true worst case, and now they are also the numbers as drawn, with no
multiplication to track.

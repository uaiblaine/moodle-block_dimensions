# Proposal — plan status filter (2026-09-22)

A learner-facing filter that scopes the plan cards by the plan's status — **Active / In review /
Completed** — loading the active plans with the page and every other status on demand, the way the
block already loads favourites first and the rest on click.

`mockup.html` beside this file is the design, drawn with the block-kit's own stylesheet (as-is,
copied verbatim) plus the four new rules its legend lists. Open it in a browser; it needs nothing
else. It was published first as an interactive canvas, which is where the owner reviewed it.

**This folder is a to-be design and a record of the decisions behind it.** `docs/block-kit/` stays
as-is, by its own rule, and must not be edited to describe anything here until it ships.

## Decisions

| # | Decision | Answer (owner, 2026-09-22) |
|---|---|---|
| D1 | Does the block render for a learner with no active plan? | **Yes** — it renders, opening on the first status that has plans. This reversed the active-only gate added the same day, and rewrote the tests that pinned it. Shipped: `dataset_provider::opening_bucket()` resolves an empty `planstatus`, so such a learner lands on their completed plans. |
| D2 | What does "In review" mean? | One bucket for *waiting for review* and *in review*, which is how core groups its draft statuses. See the capability note below: on most sites this bucket is empty for reasons that have nothing to do with the block. |
| D3 | Where does a completed plan's trail read its ratings? | From the archive core freezes on completion. Fixed first, in `local_dimensions` — see below. |
| D4 | Favourites outside "Active"? | No star and no favourites pills outside Active. |
| D5 | Templates in competencies display mode? | In the non-active buckets they render as plan cards; the competency section keeps showing active work only. |
| D6 | Search scope | The status currently selected. |

## The three facts that shaped it

**A learner cannot see their own plan while it is in review, and that is core, not this block.**
`api::list_user_plans()` filters by capability before anything in this plugin runs: the draft
statuses (`draft`, `waiting for review`, `in review`) need `moodle/competency:planviewowndraft`,
which **no archetype grants** (`lib/db/access.php`, verified on 4.5 and 5.2). The learner archetype
holds only `planviewown`, and cannot create a plan (`planmanageown`, `planmanageowndraft`) or
request a review (`planrequestreview`) either — those are manager capabilities by default. So on a
default site the "In review" bucket is empty because the query never returned those plans, not
because none exist. `block_lp` is in exactly the same position: it reads the same list, and it
cards only active plans too. The bucket is worth shipping — it is correct wherever a site grants
the capability — and its pill is simply not drawn when the count is zero.

**A completed plan's ratings are frozen by core, and the block's trail did not know that.**
`api::complete_plan()` archives every rating into `{competency_usercompplan}`, keyed by plan id, and
`api::list_plan_competencies()` reads that archive for a complete plan and the live
`{competency_usercomp}` for every other status. `local_dimensions`' `plan_trail_cache` always read
the live table, so a completed plan's trail would have shown the learner's *current* state and
disagreed with core's own plan page for the same plan. No one could see it, because the block never
rendered a completed plan — the defect would have arrived with this feature. Fixed in
`local_dimensions` (branch `completed-plan-trail-archive`): the query now reads the archive, scoped
to that plan, when the caller says the plan is complete, and the two readings are cached apart.

**Counts are free; cards are not.** The provider already loads the learner's whole plan list in its
constructor, so a count per status costs no extra query and can ride the first response. What costs
is building the cards — metadata, images, trail — and that is what the new bucket defers until the
learner asks for it.

## What implementing it touches

- `local_dimensions`: the trail fix above (done first, it is a prerequisite).
- `block_dimensions`: `has_content()` becomes "any readable plan" (D1); `dataset_provider` gains a
  status parameter and per-status counts; `get_block_dataset` gains the parameter and returns the
  counts (`execute_returns()` is an allowlist, so they must be declared) — which means a
  `version.php` bump, since services install on upgrade only; `filters.js` and `state.js` gain the
  status axis beside the existing favourites axis; `plan_card.mustache` gains the status chip; new
  lang strings in both languages; the `noactiveplans` copy is revisited, since the block can now
  render without an active plan.
- Tests: the gate tests and `visibility.feature` written on 2026-09-22 pin the opposite of D1 and
  must be rewritten with it, not merely extended.

**What shipped, against this record.** All of it, with two deliberate differences worth naming.
The status pills were briefly given a host of their own outside the collapsible filter panel, so a
phone would show them with the panel closed; the owner asked for them back in the filter bar, which
is where they are — on a phone the whole bar, status included, sits behind the toggle. And D1's
"opening on the first status that has plans" was missed in the first implementation, which always
opened on Active; it was found by review and implemented as `opening_bucket()`.

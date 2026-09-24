# JS regression harness

A behaviour suite for the block's three AMD modules, `amd/src/filters.js`, `state.js` and
`filter_tabs_nav.js`, run in headless Chromium, plus a mutation mode that proves each check can fail.
It is development tooling only: `.gitattributes` keeps `tests/jsharness` out of the release zip, and
no CI step runs it.

## Why it exists

Moodle has no unit-test runner for AMD modules. Grunt lints `amd/src` and builds `amd/build`, PHPUnit
never executes JavaScript, and Behat drives a real site, where a web service answers when it answers.
Most of what `filters.js` gets wrong lives in the order things happen: a bucket switch failing while
a search is typed, a retry landing while a load of the replaced session is still on its way, a group
load ending before the bucket it overlapped. Behat cannot hold one response back while it releases
another, so it cannot reach those states, and the plugin keeps its Behat scenarios to thin smoke
tests anyway.

This harness can. Every web service call stays pending until the scenario settles it, in whatever
order the scenario chooses.

## How it works

- `harness.html` defines a small `define()` shim and loads the real modules, in dependency order, from
  `../../amd/src/`, or from the directory named by the `src` query parameter.
- `core/ajax` is replaced by promises the scenario resolves (`respond(i, dataset)`) or rejects
  (`fail(i)`); `call(i)` is the block's i-th request, with its `args`. `core/templates` renders a stub
  that carries the card name.
- `scenarios.js` mounts a copy of the block shell per scenario and drives it through the DOM (clicks,
  key presses, the search input), then asserts on what the page shows: which cards are visible, the
  loading line, the empty-state line, the live region, focus, which requests were sent.
- `runner.py` opens the page in Chromium with `--dump-dom` under a virtual time budget, so the
  debounces and announcement delays the scenarios sleep through take no real time, and reads the
  verdict the page appends.
- `run.sh` starts `runner.py` in a pinned Chromium image with the repository mounted read-only and no
  network.

**It tests `amd/src`, not `amd/build`.** No `grunt` run is needed before the harness. The site serves
`amd/build`, though, so a change still needs `mdl grunt m502 blocks/dimensions` before a browser shows
it, and a green harness says nothing about a stale build.

## Running it

Docker is the only requirement; the image is pulled on the first run.

```sh
tests/jsharness/run.sh                                   # the suite, a few seconds
tests/jsharness/run.sh --mutants                         # the suite, then every mutant, about 20 s
tests/jsharness/run.sh --mutants --only retry-drops-focus attribution-back-in-the-docblock
tests/jsharness/run.sh --mutants --jobs 2                # fewer Chromium instances at once
```

The suite prints `suite: total=N failed=M` and one `FAIL` line per failed check, with the detail the
check recorded. With `--mutants` it prints one line per mutant and a summary:

| line | meaning |
|---|---|
| `KILLED` | a check of a family the mutant names failed, as it must |
| `SURVIVED` | no check failed: the fix the mutant undoes is not pinned, which is the finding |
| `WRONG FAMILY` | checks failed, but none of the families the mutant names |
| `NO VERDICT` | the page produced no verdict, for example because the mutant hung it |
| `NOT APPLIED` | the mutant's pattern did not match exactly once in today's `amd/src` |
| `BAD FAMILY` | the mutant names a family no check carries |

The script exits non-zero on a failed check, on a run with no verdict, and on any mutant line other
than `KILLED`. The suite must be green before the mutants run: a red suite would make every mutant
look caught.

`JSHARNESS_IMAGE` overrides the image in `run.sh`. To watch a run, serve the repository root
(`python3 -m http.server` from it) and open `/tests/jsharness/harness.html` in a browser; `runAll()`
in the console returns the verdict, and `?auto=1` runs it on load.

## Adding a check

1. Find the scenario function that already sets up the state you need, or write one and add it to
   `SCENARIOS` at the end of `scenarios.js`. Mount a block with `mount(options)`, answer its requests
   with `respond()` or `fail()` by index, and `await settle()` after each step.
2. Name each check `<family>: <what it pins>`. The family is a short behaviour name shared by the
   checks that pin one fix, such as `loading line` or `favourites disabled`.
3. Pin the state the check depends on with a `precondition` check, and add a `control` where the check
   asserts that something did not happen, so it cannot pass because nothing ran.

## Adding a mutant

Every fix the suite pins has at least one mutant in `mutants.py` that undoes it:

```python
mutant(
    'retry-drops-focus', 'filters.js', ['retry focus'],
    'The retry hides its own button with focus on it, which drops focus to the page.',
    lines(...old source lines...),
    lines(...the same lines with the fix undone...),
),
```

`old` is copied verbatim from `amd/src/<file>` and must occur there exactly once; add neighbouring
lines until it does. Run `run.sh --mutants --only <id>` and check the mutant is `KILLED` in the family
you meant, not merely by some other check. A mutant that survives means the check does not pin what
it claims: fix the check, not the mutant.

When `amd/src` changes, a `NOT APPLIED` line means the guarded code moved: port the pattern to the new
code, or drop the mutant when the guard itself is gone. A guard whose removal no scenario can observe
has no mutant; the docstring of `mutants.py` lists those, with the reason.

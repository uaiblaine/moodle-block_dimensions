# Review context for block_dimensions

`block_dimensions` is a block that shows the logged-in learner's own learning plans and the
competencies of those plans as cards, with status tabs, tag filters and a favourite star. It is
a sibling of `local_dimensions` and depends on it (and on `tool_lp`): card metadata (tags,
colours, images, trails) comes only from `local_dimensions` cache classes, and plans come from
the core competency API. It supports Moodle 4.5 through 5.2 on one branch. **It defines no
database tables of its own**; the only rows it writes are core favourites.

## Who is trusted

- Site administrators are fully trusted; the settings page is admin-only.
- The plugin declares two capabilities, both `captype` write at system context and neither
  with a `riskbitmask`: `block/dimensions:addinstance` (default `manager`, cloned from
  `moodle/site:manageblocks`) and `block/dimensions:myaddinstance` (default `user`). They only
  control who can place the block. The block is applicable on the site front page, course
  pages and the Dashboard, and renders only for a logged-in non-guest user.
- Every endpoint answers for **the current user only**: no function takes a user id. What a
  user may see of a plan is decided by the core competency API (`api::list_user_plans()`,
  `api::read_plan()`), not by this plugin. A new code path that reads another user's plan or
  favourites is a finding.
- Plan names, competency names, tag values and custom field names are untrusted input
  (administrators and teachers set them).

## Surfaces

- 3 web service functions, all `ajax`, session-based, none registering a capability in
  `db/services.php`; each calls `validate_parameters()`, `require_login()`, rejects guests and
  validates the user context of `$USER` (the one early return is the empty dataset below):
  - `block_dimensions_get_block_dataset` (read): builds the cards. `loadgroup` and
    `planstatus` are checked against fixed vocabularies; it returns an empty dataset while
    core competencies are disabled.
  - `block_dimensions_toggle_favourite` (write): `itemtype` is `plan` or `competency`. A plan
    must belong to the caller; a competency must exist. It writes through core's favourites
    service in the caller's own user context.
  - `block_dimensions_set_return_context` (write): stores a return-to-plan URL in a session
    cache for the floating button of `local_dimensions`. The plan is read with
    `api::read_plan()`, which enforces core's plan permission, and the feature is gated by a
    `local_dimensions` setting.
- No page scripts, no tasks, no hooks, no observers, no file serving, no outbound HTTP and no
  evaluation of user input. SQL uses placeholders.
- Privacy: a plugin provider with a user-context favourites export and the userlist
  provider; `db/uninstall.php` removes the plugin's `favourite` rows because core does not.

## Facts that look like findings but are by design

- **Values bound for a `style` attribute are sanitised in this plugin, not trusted from
  `local_dimensions`.** Colours pass a hex-only check (`sanitize_color()`); image URLs pass
  `PARAM_URL` plus percent-encoding of quotes, parentheses and whitespace
  (`sanitize_image_url()`), because Mustache escaping does not protect a CSS context. A new
  style-bound field without one of these is a finding.
- **The favourite guard checks existence, not visibility, for a competency.** The effect is a
  row owned by the caller, and nothing about the competency is returned.
- **Competency cards appear only for competencies of the user's own plans that link to at
  least one course with `visible = 1`** (a plain visibility test, not an enrolment test).
- **`summary::has_content()` fails open.** If the plan read throws, it logs with `error_log()`
  and returns true, so a failing read does not replace the whole Dashboard with an error page;
  `debugging()` is avoided there on purpose. A test pins it.
- **A plain draft plan is not shown**, and draft and in-review plans are hidden from learners
  by core's own `planviewowndraft` capability before this plugin sees them.
- **Return structures are an allowlist.** A field added to the dataset must also be declared
  in `get_block_dataset::execute_returns()`.
- **`styles.css` ends with a Bootstrap 4 polyfill** scoped to the block's content root, and
  colours come from theme tokens with fallbacks. Neither is a security matter.
- **One branch spans Moodle 4.5 to 5.2**, so code that branches on version or checks for a
  helper before calling it is deliberate.

## De-emphasise

- `amd/build/**` is minified output of `amd/src/**`; review the source.
- `docs/**` (design kit, screenshots, proposals), `lang/**` and `tests/**` carry no production
  behaviour.
- Visual details of `styles.css` and the Mustache templates, unless they show data the viewer
  should not see.

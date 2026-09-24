"""Mutants of amd/src that the JS harness must catch.

Each mutant undoes one fix the suite pins. runner.py --mutants copies amd/src to a temporary
directory per mutant, replaces `old` with `new` in `file` and runs the suite over the copy. A mutant
passes only when at least one check of a family it names in `reddens` fails; a mutant the suite
survives is the finding. `old` must occur exactly once in the file as it is today, so a mutant
whose code has changed fails loudly instead of testing nothing: port it to the new code, or drop it
when the code it guarded is gone.

One guard has no mutant because removing it changes nothing the page shows: the render token check
at the start of rerender()'s final pass. A render overtaken by a later one would finish with the
same shared state the later render finishes with, and one overtaken by a retry meets the
datasetReady guards of the new session, so without the check no scenario reddens.

Development tooling only: excluded from the release zip through .gitattributes.
"""


def lines(*text):
    """Join source lines, each ending in a newline."""
    return ''.join(line + '\n' for line in text)


def mutant(id, file, reddens, why, old, new):
    """One text substitution in amd/src/<file>, and the check families it must redden."""
    return {'id': id, 'file': file, 'reddens': reddens, 'why': why, 'old': old, 'new': new}


MUTANTS = [
    # The status filter.
    mutant(
        'active-pill-needs-a-count', 'filters.js', ['active pill'],
        'The Active pill disappears once the grid leaves it while its count is 0.',
        lines(
            "            || bucket.key === state.planStatus",
            "            || (bucket.key === 'active' && state.rawDataset.hasactiveplans));",
        ),
        lines(
            "            || bucket.key === state.planStatus);",
        ),
    ),
    mutant(
        'active-pill-shows-zero', 'filters.js', ['active pill'],
        'A bucket pill with no plan card reads "Active 0".',
        lines(
            "            } else if (count > 0) {",
            "                suffix = ' <span class=\"dims-filter-count\">' + count + '</span>';",
        ),
        lines(
            "            } else {",
            "                suffix = ' <span class=\"dims-filter-count\">' + count + '</span>';",
        ),
    ),
    mutant(
        'competency-load-asks-the-bucket-on-screen', 'filters.js', ['competency load bucket'],
        'A group load asks for the bucket on screen, and the server builds competency cards for Active only.',
        lines(
            "            planstatus: 'active'",
            "        })",
            "            .then((dataset) => {",
        ),
        lines(
            "            planstatus: state.planStatus",
            "        })",
            "            .then((dataset) => {",
        ),
    ),
    mutant(
        'kept-active-list-under-show-all', 'filters.js', ['kept active list'],
        'The kept favourites-only Active list comes back under "Show all", with no way to load the rest.',
        lines(
            "        state.favouriteFilterActive.plan = bucket === 'active'",
            "            && state.favouritesEnabled",
            "            && !state.normalizedSearch",
            "            && state.favouriteCountPlan > 0",
            "            && !state.fullDatasetLoaded.plan",
            "            && state.hasnonfavouriteplans;",
        ),
        lines(
            "        state.favouriteFilterActive.plan = false;",
        ),
    ),
    mutant(
        'failed-switch-stays-on-the-failed-bucket', 'filters.js', ['bucket loading'],
        'A failed bucket switch leaves the grid on the bucket that failed, so it cannot be picked again.',
        lines(
            "            state.planStatus = previous;",
            "            showBucketCards(state, previous);",
        ),
        '',
    ),
    mutant(
        'status-focus-follows-the-old-pill', 'filters.js', ['status keys'],
        'After a rebuild, focus goes back to the status pill that had it, which may be unchecked.',
        lines(
            "        if (active.matches('.dims-status-filter-btn[data-status-filter]')) {",
            "            /* Focus goes to the pill the rebuilt group checks, the group's one tab stop, rather",
            "               than to the pill that had it: that one stays unchecked when it was clicked while",
            "               another bucket was loading, or when its bucket failed to load. */",
            "            return {selector: '.dims-status-filter-btn[aria-checked=\"true\"]'};",
            "        }",
        ),
        '',
    ),
    mutant(
        'status-focus-on-the-clicked-pill', 'filters.js', ['status keys'],
        'After a rebuild, focus goes to the pill clicked rather than to the checked one.',
        lines(
            "            return {selector: '.dims-status-filter-btn[aria-checked=\"true\"]'};",
        ),
        lines(
            "            return {selector: '.dims-status-filter-btn[data-status-filter=\"' + "
            "selectorValue(active.dataset.statusFilter) + '\"]'};",
        ),
    ),
    mutant(
        'quoted-tag-value-unescaped', 'filters.js', ['quoted tag value'],
        'A tag value holding a double quote breaks the selector that finds its pill after a rebuild.',
        lines(
            "            const value = selectorValue(active.dataset.filterValue || '');",
        ),
        lines(
            "            const value = active.dataset.filterValue || '';",
        ),
    ),
    mutant(
        'no-result-reads-as-no-cards', 'filters.js', ['no-result wording'],
        'A search hiding every card reads as a dataset with no cards at all.',
        lines(
            "            empty.textContent = hasCards ? labels.resultsnonefound : labels.nocompetencies;",
        ),
        lines(
            "            empty.textContent = labels.nocompetencies;",
        ),
    ),

    # Retry, and what may happen while a bucket is on its way.
    mutant(
        'retry-keeps-the-old-session', 'filters.js', ['retry'],
        'A retry keeps the state of the session it replaces, and applies its responses.',
        lines(
            "        resetSession(state);",
            "        resetView(container, state, options.labels);",
        ),
        lines(
            "        resetView(container, state, options.labels);",
        ),
    ),
    mutant(
        'first-response-keeps-what-a-render-drew', 'filters.js', ['retry'],
        'The first response does not rebuild the lists a render drew while it was on its way.',
        lines(
            "                // its way: a search typed meanwhile renders them empty and marks them done.",
            "                resetRenderedState(container, state);",
        ),
        lines(
            "                // its way: a search typed meanwhile renders them empty and marks them done.",
        ),
    ),
    mutant(
        'plan-list-renders-while-a-bucket-loads', 'filters.js', ['bucket loading'],
        'A render while a bucket loads draws plan cards over its skeleton.',
        lines(
            "        if (type === 'plan' && state.statusLoading) {",
            "            return Promise.resolve();",
            "        }",
        ),
        '',
    ),
    mutant(
        'bucket-left-stays-in-the-dataset', 'filters.js', ['bucket loading'],
        'The cards of the bucket left stay in the dataset while the new one loads.',
        lines(
            "        state.statusLoading = bucket;",
            "        state.rawDataset.plancards = [];",
        ),
        lines(
            "        state.statusLoading = bucket;",
        ),
    ),
    mutant(
        'skeleton-hidden-by-a-render', 'filters.js', ['bucket loading'],
        'A render while a bucket loads hides its skeleton cards.',
        lines(
            "        list.querySelectorAll('.dims-card-item:not(.dims-ghost-card):not(.dims-skeleton-card)')"
            ".forEach((item) => {",
        ),
        lines(
            "        list.querySelectorAll('.dims-card-item:not(.dims-ghost-card)').forEach((item) => {",
        ),
    ),
    mutant(
        'empty-line-while-a-bucket-loads', 'filters.js', ['bucket loading'],
        'The empty-state line speaks for a bucket that has not arrived.',
        lines(
            "        // has no result yet: the loading line, and a bucket's skeleton, stand in until then.",
            "        if (!state.datasetReady || state.statusLoading) {",
        ),
        lines(
            "        // has no result yet: the loading line, and a bucket's skeleton, stand in until then.",
            "        if (!state.datasetReady) {",
        ),
    ),
    mutant(
        'announcement-while-a-bucket-loads', 'filters.js', ['bucket loading'],
        'The results announcement counts the grid of a bucket that has not arrived.',
        lines(
            "            // loading counts no plan: the render that follows the response announces the real count.",
            "            if (!state.datasetReady || state.statusLoading) {",
        ),
        lines(
            "            // loading counts no plan: the render that follows the response announces the real count.",
            "            if (!state.datasetReady) {",
        ),
    ),
    mutant(
        'stale-bucket-response-applied', 'filters.js', ['retry'],
        'A bucket response of the session a retry replaced is applied.',
        lines(
            "            if (session !== state.session) {",
            "                return null;",
            "            }",
            "            state.statusLoading = null;",
        ),
        lines(
            "            state.statusLoading = null;",
        ),
    ),
    mutant(
        'stale-group-response-applied', 'filters.js', ['retry'],
        'A group response of the session a retry replaced is applied.',
        lines(
            "                if (session !== state.session) {",
            "                    return null;",
            "                }",
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
        ),
        lines(
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
        ),
    ),
    mutant(
        'status-keys-move-while-busy', 'filter_tabs_nav.js', ['status keys'],
        'The arrow, Home and End keys move focus off the checked pill while its bucket loads.',
        lines(
            "        if (this.itemsEl.querySelector('.dims-filter-tab[aria-busy=\"true\"]')) {",
            "            return;",
            "        }",
        ),
        '',
    ),

    # The loading line.
    mutant(
        'group-load-end-hides-the-line', 'filters.js', ['loading line'],
        'A group load ending hides the loading line while a bucket is still on its way.',
        lines(
            "        state.groupLoads--;",
            "        syncLoadingLine(container, state, labels);",
        ),
        lines(
            "        state.groupLoads--;",
            "        container.querySelector('.dims-loading-state').style.display = 'none';",
        ),
    ),
    mutant(
        'bucket-text-over-a-group-load', 'filters.js', ['loading line'],
        'A bucket loading beside a group load names the bucket, as if it were all that is pending.',
        lines(
            "        if (state.groupLoads > 0) {",
            "            message = loading.dataset.defaultText;",
            "        } else if (state.statusLoading) {",
            "            message = labels[BUCKET_LOADING_LABELS[state.statusLoading]] || loading.dataset.defaultText;",
            "        }",
        ),
        lines(
            "        if (state.statusLoading) {",
            "            message = labels[BUCKET_LOADING_LABELS[state.statusLoading]] || loading.dataset.defaultText;",
            "        } else if (state.groupLoads > 0) {",
            "            message = loading.dataset.defaultText;",
            "        }",
        ),
    ),
    mutant(
        'skeleton-leaves-the-line-alone', 'filters.js', ['loading line'],
        'Drawing the bucket skeleton does not bring the loading line up.',
        lines(
            "            list.setAttribute('aria-busy', 'true');",
            "        }",
            "",
            "        syncLoadingLine(container, state, labels);",
        ),
        lines(
            "            list.setAttribute('aria-busy', 'true');",
            "        }",
        ),
    ),
    mutant(
        'active-loading-reads-the-review-text', 'filters.js', ['loading line'],
        'The Active bucket loading reads the In review text.',
        lines(
            "            message = labels[BUCKET_LOADING_LABELS[state.statusLoading]] || loading.dataset.defaultText;",
        ),
        lines(
            "            message = (state.statusLoading === 'complete' ? labels.statusloadingcomplete : "
            "labels.statusloadingreview)",
            "                || loading.dataset.defaultText;",
        ),
    ),

    # A search the first load finds.
    mutant(
        'search-at-load-keeps-favourites-first', 'filters.js', ['search at load'],
        'A first load under a search stays favourites-only, so the search misses the other cards.',
        lines(
            "                if (useFavouritesFirst && hasAnyNonFavourites && state.normalizedSearch) {",
        ),
        lines(
            "                if (useFavouritesFirst && hasAnyNonFavourites && state.normalizedSearch && false) {",
        ),
    ),
    mutant(
        'restored-value-hides-the-clear-button', 'filters.js', ['search at load'],
        'A search value the browser put back in the input gets no clear button.',
        lines(
            "        if (searchInput && clearButton) {",
            "            clearButton.style.display = searchInput.value.length ? 'flex' : 'none';",
            "        }",
        ),
        '',
    ),
    mutant(
        'load-ignores-the-input-value', 'filters.js', ['search at load'],
        'A load does not read the search term the input already holds.',
        lines(
            "        if (searchInput) {",
            "            state.searchTerm = searchInput.value.trim();",
            "            state.normalizedSearch = normalizeText(state.searchTerm);",
            "        }",
        ),
        '',
    ),
    mutant(
        'empty-line-before-the-first-dataset', 'filters.js', ['before the first dataset'],
        'A search typed before the first dataset shows the empty-state line.',
        lines(
            "        // has no result yet: the loading line, and a bucket's skeleton, stand in until then.",
            "        if (!state.datasetReady || state.statusLoading) {",
        ),
        lines(
            "        // has no result yet: the loading line, and a bucket's skeleton, stand in until then.",
            "        if (state.statusLoading) {",
        ),
    ),
    mutant(
        'announcement-before-the-first-dataset', 'filters.js', ['before the first dataset'],
        'A search typed before the first dataset announces a result.',
        lines(
            "            // loading counts no plan: the render that follows the response announces the real count.",
            "            if (!state.datasetReady || state.statusLoading) {",
        ),
        lines(
            "            // loading counts no plan: the render that follows the response announces the real count.",
            "            if (state.statusLoading) {",
        ),
    ),

    # Focus on retry.
    mutant(
        'retry-drops-focus', 'filters.js', ['retry focus'],
        'The retry hides its own button with focus on it, which drops focus to the page.',
        lines(
            "                if (document.activeElement === retryButton) {",
            "                    focusBlock(container);",
            "                }",
        ),
        lines(
            "                if (document.activeElement === retryButton) {",
            "                }",
        ),
    ),
    mutant(
        'retry-always-takes-focus', 'filters.js', ['retry focus'],
        'A retry started while focus is elsewhere moves focus anyway.',
        lines(
            "                if (document.activeElement === retryButton) {",
            "                    focusBlock(container);",
            "                }",
        ),
        lines(
            "                focusBlock(container);",
        ),
    ),
    mutant(
        'block-keeps-its-tabindex', 'filters.js', ['retry focus'],
        'The block stays a tab stop after focus leaves it.',
        lines(
            "            container.addEventListener('blur', () => container.removeAttribute('tabindex'), {once: true});",
        ),
        '',
    ),

    # A failed bucket switch.
    mutant(
        'failed-switch-drops-the-filters', 'filters.js', ['failed switch keeps filters'],
        'A failed switch returns to the bucket left without the filters it had.',
        lines(
            "            state.favouriteFilterActive.plan = filters.favourite && state.favouritesEnabled;",
            "            /* eslint-disable camelcase */",
            "            state.activeFilters.plan_tag1 = filters.tag1;",
            "            state.activeFilters.plan_tag2 = filters.tag2;",
            "            /* eslint-enable camelcase */",
        ),
        '',
    ),
    mutant(
        'failed-switch-drops-the-favourites-filter', 'filters.js', ['failed switch keeps filters'],
        'A failed switch restores the tag filters of the bucket left but not its favourites filter.',
        lines(
            "            state.favouriteFilterActive.plan = filters.favourite && state.favouritesEnabled;",
        ),
        '',
    ),
    mutant(
        'any-search-lifts-the-filters', 'filters.js', ['failed switch under a search'],
        'A search set before the switch lifts the filters the learner picked over it.',
        lines(
            "            const searched = state.normalizedSearch !== '' && state.normalizedSearch !== previousSearch;",
        ),
        lines(
            "            const searched = state.normalizedSearch !== '';",
        ),
    ),
    mutant(
        'failed-switch-keeps-filters-under-a-search', 'filters.js', ['failed switch under a search'],
        'A search typed while the bucket loaded does not lift the filters of the bucket gone back to.',
        lines(
            "            const filters = searched ? {favourite: false, tag1: '', tag2: ''} : previousFilters;",
        ),
        lines(
            "            const filters = previousFilters;",
        ),
    ),
    mutant(
        'failed-switch-fetches-nothing-under-a-search', 'filters.js', ['failed switch under a search'],
        'A failed switch under a search leaves the Active list at its favourites, so the search misses plans.',
        lines(
            "            if (searched && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
        ),
        lines(
            "            loadWhatFavouritesLeftOut(container, state, options);",
        ),
    ),

    # Every response goes through applySharedMetadata().
    mutant(
        'bucket-response-skips-shared-metadata', 'filters.js', ['shared metadata'],
        'A bucket response leaves hasactiveplans, the counts and the filter settings stale.',
        lines(
            "            clearStatusSkeleton(container, state, options.labels);",
            "            applySharedMetadata(state, dataset);",
        ),
        lines(
            "            clearStatusSkeleton(container, state, options.labels);",
        ),
    ),
    mutant(
        'group-response-skips-shared-metadata', 'filters.js', ['shared metadata'],
        'A group response leaves hasactiveplans, the counts and the filter settings stale.',
        lines(
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
            "                applySharedMetadata(state, dataset);",
        ),
        lines(
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
        ),
    ),
    mutant(
        'first-response-skips-shared-metadata', 'filters.js', ['shared metadata'],
        'The first response leaves hasactiveplans, the counts and the filter settings stale.',
        lines(
            "                applySharedMetadata(state, dataset);",
            "                state.datasetReady = true;",
        ),
        lines(
            "                state.datasetReady = true;",
        ),
    ),
    mutant(
        'shared-metadata-skips-hasactiveplans', 'state.js', ['shared metadata'],
        'applySharedMetadata() stores everything but hasactiveplans.',
        lines(
            "        state.rawDataset.hasactiveplans = !!dataset.hasactiveplans;",
        ),
        '',
    ),

    # Back on Active under a search.
    mutant(
        'search-keeps-the-favourites-filter-on-active', 'filters.js', ['back on active under a search'],
        'The kept favourites-only Active list comes back under its filter although a search is set.',
        lines(
            "            && state.favouritesEnabled",
            "            && !state.normalizedSearch",
        ),
        lines(
            "            && state.favouritesEnabled",
        ),
    ),
    mutant(
        'back-on-active-fetches-nothing-under-a-search', 'filters.js', ['back on active under a search'],
        'Back on the kept Active list under a search, the plans it is missing are not fetched.',
        lines(
            "            if (state.normalizedSearch && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
            "            return Promise.resolve();",
        ),
        lines(
            "            loadWhatFavouritesLeftOut(container, state, options);",
            "            return Promise.resolve();",
        ),
    ),
    mutant(
        'any-search-fetches-the-active-plans', 'filters.js', ['back on active under a search'],
        'Back on Active under a search, the plans are fetched even when the list is already whole.',
        lines(
            "            if (state.normalizedSearch && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
            "            return Promise.resolve();",
        ),
        lines(
            "            if (state.normalizedSearch) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
            "            return Promise.resolve();",
        ),
    ),

    # Favourites disabled by a response in the middle of a session.
    mutant(
        'disabled-favourites-keep-their-filters', 'state.js', ['favourites disabled'],
        'A response disabling favourites leaves the favourites filters on, with no pill to undo them.',
        lines(
            "        if (!state.favouritesEnabled) {",
            "            state.favouriteFilterActive.plan = false;",
            "            state.favouriteFilterActive.competency = false;",
            "        }",
        ),
        '',
    ),
    mutant(
        'bucket-response-leaves-the-rest-unfetched', 'filters.js', ['favourites disabled'],
        'A bucket response disabling favourites does not fetch what they left out.',
        lines(
            "            rerender(container, state, options);",
            "            loadWhatFavouritesLeftOut(container, state, options);",
            "            return null;",
        ),
        lines(
            "            rerender(container, state, options);",
            "            return null;",
        ),
    ),
    mutant(
        'group-response-leaves-the-rest-unfetched', 'filters.js', ['favourites disabled'],
        'A group response disabling favourites does not fetch what they left out.',
        lines(
            "                const next = loadWhatFavouritesLeftOut(container, state, options);",
        ),
        lines(
            "                const next = null;",
        ),
    ),
    mutant(
        'kept-list-under-favourites-while-disabled', 'filters.js', ['favourites disabled'],
        'The kept Active list comes back under the favourites filter although favourites are disabled.',
        lines(
            "        state.favouriteFilterActive.plan = bucket === 'active'",
            "            && state.favouritesEnabled",
        ),
        lines(
            "        state.favouriteFilterActive.plan = bucket === 'active'",
        ),
    ),
    mutant(
        'failed-switch-restores-a-disabled-favourites-filter', 'filters.js', ['favourites disabled'],
        'A failed switch restores the favourites filter a response disabled during it.',
        lines(
            "            state.favouriteFilterActive.plan = filters.favourite && state.favouritesEnabled;",
        ),
        lines(
            "            state.favouriteFilterActive.plan = filters.favourite;",
        ),
    ),
    mutant(
        'back-on-active-leaves-the-rest-unfetched', 'filters.js', ['favourites disabled'],
        'Back on the kept Active list with favourites disabled, the plans they left out are not fetched.',
        lines(
            "            if (state.normalizedSearch && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
            "            return Promise.resolve();",
        ),
        lines(
            "            if (state.normalizedSearch && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            }",
            "            return Promise.resolve();",
        ),
    ),
    mutant(
        'failed-switch-leaves-the-rest-unfetched', 'filters.js', ['favourites disabled'],
        'A failed switch with favourites disabled leaves the Active list at its favourites.',
        lines(
            "            if (searched && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            } else {",
            "                loadWhatFavouritesLeftOut(container, state, options);",
            "            }",
        ),
        lines(
            "            if (searched && searchNeedsPlans(state)) {",
            "                loadGroupDataset(container, state, options, 'plan');",
            "            }",
        ),
    ),
    mutant(
        'rest-fetched-with-favourites-enabled', 'filters.js', ['favourites disabled'],
        'What the favourites-only request left out is fetched even while favourites are enabled.',
        lines(
            "        if (state.favouritesEnabled || (!plans && !competencies)) {",
        ),
        lines(
            "        if (!plans && !competencies) {",
        ),
    ),

    # No "no result" while a pending load can still add cards.
    mutant(
        'empty-line-ignores-pending-loads', 'filters.js', ['pending load holds no-result'],
        'The empty-state line says no result while a load on its way can still add cards.',
        lines(
            "            if (!canClaimNoResult(container, state)) {",
            "                return;",
            "            }",
            "            // When the search or a filter hides every card, the cards exist: say no result matched.",
        ),
        lines(
            "            if (!canClaimNoResult(container, state) && !groupLoadCanAddCards(state)) {",
            "                return;",
            "            }",
            "            // When the search or a filter hides every card, the cards exist: say no result matched.",
        ),
    ),
    mutant(
        'announcement-ignores-pending-loads', 'filters.js', ['pending load holds no-result'],
        'The results announcement says no result while a load on its way can still add cards.',
        lines(
            "                if (!canClaimNoResult(container, state)) {",
            "                    return;",
            "                }",
            "                message = labels.resultsnonefound || '';",
        ),
        lines(
            "                if (!canClaimNoResult(container, state) && !groupLoadCanAddCards(state)) {",
            "                    return;",
            "                }",
            "                message = labels.resultsnonefound || '';",
        ),
    ),
    mutant(
        'pending-plans-hold-every-bucket', 'filters.js', ['pending load holds no-result'],
        'Active plans on their way hold the no-result line of a bucket they cannot reach.',
        lines(
            "            || (!!state.groupRequests.plan && state.planStatus === 'active');",
        ),
        lines(
            "            || !!state.groupRequests.plan;",
        ),
    ),
    mutant(
        'pending-competencies-hold-nothing', 'filters.js', ['pending load holds no-result'],
        'Competency cards on their way do not hold the no-result line.',
        lines(
            "        return !!state.groupRequests.competency",
            "            || (!!state.groupRequests.plan && state.planStatus === 'active');",
        ),
        lines(
            "        return (!!state.groupRequests.plan && state.planStatus === 'active');",
        ),
    ),
    mutant(
        'failed-load-leaves-the-empty-line-unsettled', 'filters.js', ['pending load holds no-result'],
        'A group load failing leaves the empty-state line it held unsettled, since no render follows.',
        lines(
            "                // No render follows a failure: the empty-state line held for this load is settled here.",
            "                updateEmptyState(container, state, options.labels);",
        ),
        '',
    ),

    # One load per card type.
    mutant(
        'card-type-on-its-way-asked-again', 'filters.js', ['one load per card type'],
        'A card type already on its way is requested again.',
        lines(
            "        const loadPlans = wantPlans && !state.groupRequests.plan;",
            "        const loadCompetencies = wantCompetencies && !state.groupRequests.competency;",
        ),
        lines(
            "        const loadPlans = wantPlans;",
            "        const loadCompetencies = wantCompetencies;",
        ),
    ),
    mutant(
        'any-pending-load-holds-both-types', 'filters.js', ['one load per card type'],
        'Plans on their way hold back a load of the competency cards, and the other way round.',
        lines(
            "        const loadPlans = wantPlans && !state.groupRequests.plan;",
            "        const loadCompetencies = wantCompetencies && !state.groupRequests.competency;",
        ),
        lines(
            "        const busy = !!state.groupRequests.plan || !!state.groupRequests.competency;",
            "        const loadPlans = wantPlans && !busy;",
            "        const loadCompetencies = wantCompetencies && !busy;",
        ),
    ),
    mutant(
        'reused-load-not-waited-for', 'filters.js', ['one load per card type'],
        'A caller reusing a load on its way does not wait for it, so it renders before the cards land.',
        lines(
            "            return Promise.all(pending);",
        ),
        lines(
            "            return Promise.resolve();",
        ),
    ),
    mutant(
        'landed-load-stays-recorded', 'filters.js', ['pending load holds no-result'],
        'A group load that landed stays recorded as on its way, so no result can ever be claimed.',
        lines(
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
            "                applySharedMetadata(state, dataset);",
        ),
        lines(
            "                applySharedMetadata(state, dataset);",
        ),
    ),
    mutant(
        'failed-load-stays-recorded', 'filters.js', ['favourites disabled', 'pending load holds no-result'],
        'A group load that failed stays recorded as on its way, so it is never asked for again.',
        lines(
            "                forgetGroupRequest(state, loadPlans, loadCompetencies);",
            "                endGroupLoad(container, state, options.labels);",
        ),
        lines(
            "                endGroupLoad(container, state, options.labels);",
        ),
    ),
    mutant(
        'new-session-keeps-old-loads', 'state.js', ['one load per card type'],
        'A retry keeps the loads of the replaced session recorded, and waits for responses it drops.',
        lines(
            "        fresh.session = state.session + 1;",
        ),
        lines(
            "        fresh.session = state.session + 1;",
            "        fresh.groupRequests = state.groupRequests;",
        ),
    ),

    # No "no result" while a ghost card offers more.
    mutant(
        'no-result-claimed-under-a-ghost-card', 'filters.js', ['ghost card holds no-result'],
        'canClaimNoResult() ignores a ghost card offering more items.',
        lines(
            "        const ghostCard = container.querySelector('.dims-ghost-card');",
            "        if (ghostCard && ghostCard.style.display !== 'none') {",
            "            return false;",
            "        }",
            "        return !groupLoadCanAddCards(state);",
        ),
        lines(
            "        return !groupLoadCanAddCards(state);",
        ),
    ),
    mutant(
        'empty-line-ignores-the-ghost-card', 'filters.js', ['ghost card holds no-result'],
        'The empty-state line says no result while a ghost card offers more items.',
        lines(
            "            if (!canClaimNoResult(container, state)) {",
            "                return;",
            "            }",
            "            // When the search or a filter hides every card, the cards exist: say no result matched.",
        ),
        lines(
            "            if (groupLoadCanAddCards(state)) {",
            "                return;",
            "            }",
            "            // When the search or a filter hides every card, the cards exist: say no result matched.",
        ),
    ),
    mutant(
        'announcement-ignores-the-ghost-card', 'filters.js', ['ghost card holds no-result'],
        'The results announcement says no result while a ghost card offers more items.',
        lines(
            "                if (!canClaimNoResult(container, state)) {",
            "                    return;",
            "                }",
            "                message = labels.resultsnonefound || '';",
        ),
        lines(
            "                if (groupLoadCanAddCards(state)) {",
            "                    return;",
            "                }",
            "                message = labels.resultsnonefound || '';",
        ),
    ),

    # The docblock of filter_tabs_nav.js.
    mutant(
        'attribution-back-in-the-docblock', 'filter_tabs_nav.js', ['source docblock'],
        'The module docblock names a design it was adapted from again, in place of its lead-in.',
        lines(
            " * Provides:",
        ),
        lines(
            " * Adapted from a pill tab navigation pattern. Provides:",
        ),
    ),

    # Announcements of two blocks on one page.
    mutant(
        'one-announcement-timer-for-every-block', 'filters.js', ['announcement per block'],
        'Two blocks on one page share one announcement timer and cancel each other.',
        lines(
            "        clearTimeout(resultsAnnounceTimers.get(container));",
            "        resultsAnnounceTimers.set(container, setTimeout(function() {",
        ),
        lines(
            "        clearTimeout(resultsAnnounceTimers.get(resultsAnnounceTimers));",
            "        resultsAnnounceTimers.set(resultsAnnounceTimers, setTimeout(function() {",
        ),
    ),
]

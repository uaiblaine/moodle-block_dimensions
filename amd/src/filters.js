// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side state-driven rendering for block_dimensions.
 *
 * Supports two-phase loading: with favourites enabled, only favourite cards are
 * loaded first for a fast initial render, and the rest of a card type is fetched
 * on demand (for example by a search, the "Show all" pill or the ghost card).
 * Plan cards are also scoped to a status bucket; see pickStatus().
 *
 * @module     block_dimensions/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-disable jsdoc/require-jsdoc, jsdoc/require-param, jsdoc/check-param-names, max-len */

define(['core/ajax', 'core/templates', 'block_dimensions/filter_tabs_nav', 'block_dimensions/state'],
    function(Ajax, Templates, FilterTabsNav, State) {
    const BATCH_SIZE = 24;

    const normalizeText = State.normalizeText;
    const createState = State.createState;
    const resetSession = State.resetSession;
    const applyFilters = State.applyFilters;
    const hasActiveFiltersForType = State.hasActiveFiltersForType;
    const updateFavouriteCounts = State.updateFavouriteCounts;

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    function fetchDataset(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args || {}}])[0];
    }

    function getUniqueTagValues(cards, tag) {
        const values = {};
        cards.forEach((card) => {
            if (card[tag]) {
                values[card[tag]] = true;
            }
        });
        return Object.keys(values).sort((a, b) => a.localeCompare(b));
    }

    /**
     * Append a count to a label, e.g. "Show all" + 15 → "Show all (15)".
     */
    function labelWithCount(label, count) {
        return label + ' (' + count + ')';
    }

    /**
     * Push the plan status pills: Active, In review, Completed.
     *
     * They sit inside the filter bar, so on a phone they open and close with the other filters.
     * A bucket with no plans is not drawn unless it is the one on screen: an empty "In review"
     * would read as "you have none", while on most sites it means "you cannot see them" - the
     * review statuses are core's draft statuses and need moodle/competency:planviewowndraft, which
     * no archetype holds. A single bucket is not a choice, so nothing is drawn unless two qualify.
     *
     * The count is the number of plan cards the bucket shows. An active plan whose template shows
     * competency cards adds none, so the Active pill is drawn whenever the learner has an active
     * plan (hasactiveplans), and without a number when its count is 0: "Active 0" above a list of
     * competency cards would be wrong.
     *
     * @param {Array} html Markup accumulator, appended in place.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     * @param {string} groupLabel Fallback accessible name for the radiogroup.
     * @return {boolean} Whether anything was drawn.
     */
    function renderStatusPills(html, state, labels, groupLabel) {
        const buckets = [
            {key: 'active', label: labels.statusactive},
            {key: 'review', label: labels.statusreview},
            {key: 'complete', label: labels.statuscomplete}
        ].filter((bucket) => (state.planCounts[bucket.key] || 0) > 0
            || bucket.key === state.planStatus
            || (bucket.key === 'active' && state.rawDataset.hasactiveplans));

        if (buckets.length < 2) {
            return false;
        }

        html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="planstatus">');
        html.push('<div class="dims-filter-tabs" role="radiogroup" aria-label="'
            + escapeHtml(labels.statusfilter || groupLabel) + '">');
        buckets.forEach((bucket) => {
            const isSelected = state.planStatus === bucket.key;
            const isBusy = state.statusLoading === bucket.key;
            const count = state.planCounts[bucket.key] || 0;
            let suffix = '';
            if (isBusy) {
                suffix = ' <i class="fa fa-spinner fa-spin dims-status-spinner" aria-hidden="true"></i>';
            } else if (count > 0) {
                suffix = ' <span class="dims-filter-count">' + count + '</span>';
            }
            html.push('<button type="button" class="dims-filter-tab dims-status-filter-btn'
                + (isSelected ? ' active' : '') + '" role="radio" aria-checked="'
                + (isSelected ? 'true' : 'false') + '" tabindex="' + (isSelected ? '0' : '-1')
                + '" aria-busy="' + (isBusy ? 'true' : 'false')
                + '" data-status-filter="' + bucket.key + '">'
                + escapeHtml(bucket.label || bucket.key)
                + suffix
                + '</button>');
        });
        html.push('</div></div>');

        return true;
    }

    /**
     * Push the favourites / show-all pill pair for one card type.
     *
     * Not drawn when favourites are disabled or the type has no favourite, nor for plans outside
     * the active status bucket, whose cards carry no favourite toggle.
     *
     * @param {Array} html Markup accumulator, appended in place.
     * @param {string} type 'plan' or 'competency'.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     * @param {string} groupLabel Accessible name for the radiogroup.
     * @return {boolean} Whether anything was drawn.
     */
    function renderFavouritePills(html, type, state, labels, groupLabel) {
        if (!state.favouritesEnabled || (type === 'plan' && state.planStatus !== 'active')) {
            return false;
        }

        const typeFavCount = type === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
        if (typeFavCount <= 0) {
            return false;
        }

        const typeTotal = type === 'plan' ? state.totalplans : state.totalcompetencies;
        const isFavActive = state.favouriteFilterActive[type];

        html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="fav_' + type + '">');
        html.push('<div class="dims-filter-tabs" role="radiogroup" aria-label="' + escapeHtml(groupLabel) + '">');

        // "My Favourites (N)" pill — radio button.
        html.push('<button type="button" class="dims-filter-tab dims-fav-filter-btn'
            + (isFavActive ? ' active' : '') + '" role="radio" aria-checked="'
            + (isFavActive ? 'true' : 'false') + '" tabindex="' + (isFavActive ? '0' : '-1')
            + '" data-fav-filter-type="' + type + '">'
            + '<i class="fa fa-star dims-fav-filter-icon" aria-hidden="true"></i> '
            + escapeHtml(labels.myfavourites)
            + ' <span class="dims-filter-count">' + typeFavCount + '</span>'
            + '</button>');

        // "Show all (N)" pill — radio button.
        html.push('<button type="button" class="dims-filter-tab dims-all-filter-btn'
            + (!isFavActive ? ' active' : '') + '" role="radio" aria-checked="'
            + (!isFavActive ? 'true' : 'false') + '" tabindex="' + (!isFavActive ? '0' : '-1')
            + '" data-all-filter-type="' + type + '">'
            + escapeHtml(labels.showallitems) + ' <span class="dims-filter-count">' + typeTotal + '</span>'
            + '</button>');

        html.push('</div></div>');

        return true;
    }

    /**
     * Build the filter bar of one card type into its host.
     *
     * This is the only definition of the bar's markup; no template renders it. Every pill set is
     * a radiogroup of radio buttons carrying aria-checked, not a tablist, which would need a
     * tabpanel for each tab. A tag filter in dropdown mode is a native select. The bar is left
     * empty when none of its groups has anything to offer.
     *
     * @param {HTMLElement} container Block container.
     * @param {string} type 'plan' or 'competency'.
     * @param {Array} cards The type's cards, whose tag values become the tag filter options.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function renderFilterControls(container, type, cards, state, labels) {
        const host = container.querySelector('.dims-filters-host[data-filters-type="' + type + '"]');
        if (!host) {
            return;
        }

        const settings = state.filterSettings[type] || {};
        const html = [];
        let hasAnyFilter = false;
        // Accessible name of the toolbar; the pill groups and tag filters in it reuse or
        // extend it (WCAG 2.4.6, 4.1.2).
        const groupLabel = type === 'plan'
            ? (labels.filterbyplan || labels.filterall)
            : (labels.filterbycompetency || labels.filterall);

        html.push('<div class="dims-filters-bar" role="toolbar" aria-label="' + escapeHtml(groupLabel) + '">');

        // Status pills (plans only) come first, then the favourites / show-all pair. Each pill set
        // is a radiogroup (WCAG 4.1.2) with a roving tabindex: only the checked pill has
        // tabindex="0", so the group is one tab stop and filter_tabs_nav.js moves within it on
        // arrow keys.
        if (type === 'plan') {
            hasAnyFilter = renderStatusPills(html, state, labels, groupLabel) || hasAnyFilter;
        }

        hasAnyFilter = renderFavouritePills(html, type, state, labels, groupLabel) || hasAnyFilter;

        ['tag1', 'tag2'].forEach((tag) => {
            const enabledKey = tag + 'enabled';
            const modeKey = tag + 'displaymode';
            if (!settings[enabledKey]) {
                return;
            }

            const field = type + '_' + tag;
            const values = getUniqueTagValues(cards, tag);
            if (!values.length) {
                return;
            }

            hasAnyFilter = true;
            const mode = settings[modeKey] === 'dropdown' ? 'dropdown' : 'tabs';
            const selectedValue = state.activeFilters[field] || '';
            // Combine the type-specific label ("Filter learning plans") with
            // the admin-configured custom-field name ("Year") when available,
            // so each radiogroup/select has a unique, meaningful name
            // (WCAG 2.4.6, 4.1.2). Falls back to the generic group label when
            // the customfield isn't defined.
            const tagLabel = settings[tag + 'label'];
            const fieldLabel = tagLabel ? (groupLabel + ' — ' + tagLabel) : groupLabel;

            if (mode === 'tabs') {
                html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="' + field + '">');
                html.push('<div class="dims-filter-tabs" role="radiogroup" aria-label="' + escapeHtml(fieldLabel) + '">');
                html.push('<button type="button" class="dims-filter-tab ' + (selectedValue === '' ? 'active' : '')
                    + '" role="radio" aria-checked="' + (selectedValue === '' ? 'true' : 'false')
                    + '" tabindex="' + (selectedValue === '' ? '0' : '-1')
                    + '" data-filter-field="' + field + '" data-filter-value="">' + escapeHtml(labels.filterall) + '</button>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<button type="button" class="dims-filter-tab ' + (isSelected ? 'active' : '')
                        + '" role="radio" aria-checked="' + (isSelected ? 'true' : 'false')
                        + '" tabindex="' + (isSelected ? '0' : '-1')
                        + '" data-filter-field="' + field + '" data-filter-value="' + escapeHtml(value) + '">'
                        + escapeHtml(value) + '</button>');
                });
                html.push('</div></div>');
            } else {
                html.push('<div class="dims-filter-dropdown-wrapper" data-filter-group="' + field + '">');
                html.push('<select class="dims-filter-select" data-filter-field="' + field
                    + '" aria-label="' + escapeHtml(fieldLabel) + '">');
                html.push('<option value=""' + (selectedValue === '' ? ' selected' : '') + '>' + escapeHtml(labels.filterall) + '</option>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(value) + '</option>');
                });
                html.push('</select></div>');
            }
        });

        // "Clear filters" button — rendered inside the bar, shown/hidden dynamically.
        if (hasAnyFilter) {
            html.push('<button type="button" class="dims-clear-filters-btn" data-clear-type="'
                + type + '" style="display:none" aria-label="' + escapeHtml(labels.clearfilters || 'Clear filters') + '">'
                + '<i class="fa fa-eraser" aria-hidden="true"></i> '
                + escapeHtml(labels.clearfilters || 'Clear filters')
                + '</button>');
        }

        html.push('</div>');
        if (hasAnyFilter) {
            // Destroy existing tab nav instances before replacing HTML.
            FilterTabsNav.destroyAll(host);
            host.innerHTML = '<div class="dims-filters-panel-inner">' + html.join('') + '</div>';
            // Initialize horizontal-scrolling tab navigation. Pass labels so
            // paddle aria-labels can be localized (WCAG 3.1.2).
            FilterTabsNav.initAll(host, {labels: labels});
        } else {
            FilterTabsNav.destroyAll(host);
            host.innerHTML = '';
        }
    }

    function syncFilterActiveState(container, state) {
        // Tag-value radios. Each radiogroup keeps a single tab stop: the
        // currently checked radio gets tabindex="0", the others tabindex="-1"
        // (roving tabindex pattern, WCAG 2.1.1 + 2.4.3).
        container.querySelectorAll(
            '.dims-filter-tab:not(.dims-fav-filter-btn):not(.dims-all-filter-btn):not(.dims-status-filter-btn)'
        ).forEach(function(tab) {
            var field = tab.dataset.filterField;
            var value = tab.dataset.filterValue || '';
            var isActive = (state.activeFilters[field] || '') === value;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-checked', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        container.querySelectorAll('.dims-filter-select').forEach(function(select) {
            var field = select.dataset.filterField;
            select.value = state.activeFilters[field] || '';
        });

        // Sync favourite / all pills (also a radiogroup).
        container.querySelectorAll('.dims-fav-filter-btn').forEach(function(btn) {
            var favType = btn.dataset.favFilterType;
            var isActive = state.favouriteFilterActive[favType];
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
            btn.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        container.querySelectorAll('.dims-all-filter-btn').forEach(function(btn) {
            var allType = btn.dataset.allFilterType;
            var isActive = !state.favouriteFilterActive[allType];
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
            btn.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        // Update the animated indicator position in all tab nav instances.
        FilterTabsNav.updateAll(container);
    }

    /**
     * Show or hide the "Clear filters" button for each type based on active state.
     */
    function updateClearFilterButtons(container, state) {
        container.querySelectorAll('.dims-clear-filters-btn').forEach(function(btn) {
            var clearType = btn.dataset.clearType;
            btn.style.display = hasActiveFiltersForType(state, clearType) ? '' : 'none';
        });
    }

    function renderCardItem(type, card) {
        const templatename = type === 'plan' ? 'block_dimensions/plan_card' : 'block_dimensions/competency_card';

        return Templates.render(templatename, card).then((html, js) => {
            const li = document.createElement('li');
            li.dataset.cardId = String(card.id);

            if (type === 'plan') {
                li.className = 'dims-plan-item mb-3 dims-card-item ' +
                    (card.ishorizontal ? 'dims-plan-item-horizontal col-12 col-lg-6 col-xl-4' : 'dims-plan-item-vertical col-12 col-sm-6 col-lg-4');
                li.dataset.planTag1 = card.tag1 || '';
                li.dataset.planTag2 = card.tag2 || '';
            } else {
                li.className = 'mb-3 dims-card-item dims-competency-item';
                li.dataset.competencyTag1 = card.tag1 || '';
                li.dataset.competencyTag2 = card.tag2 || '';
            }

            li.innerHTML = html;
            if (js && typeof Templates.runTemplateJS === 'function') {
                Templates.runTemplateJS(js);
            }
            return li;
        });
    }

    function renderCardListIncremental(container, type, cards, token) {
        const list = container.querySelector('[data-cards-type="' + type + '"]');
        if (!list) {
            return Promise.resolve();
        }

        list.innerHTML = '';
        if (!cards.length) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const renderBatch = (start) => {
                if (token !== container.dataset.renderToken) {
                    resolve();
                    return;
                }

                const batch = cards.slice(start, start + BATCH_SIZE);
                if (!batch.length) {
                    resolve();
                    return;
                }

                Promise.all(batch.map((card) => renderCardItem(type, card)))
                    .then((nodes) => {
                        if (token !== container.dataset.renderToken) {
                            resolve();
                            return null;
                        }

                        const fragment = document.createDocumentFragment();
                        nodes.forEach((node) => fragment.appendChild(node));
                        list.appendChild(fragment);

                        globalThis.requestAnimationFrame(() => renderBatch(start + BATCH_SIZE));
                        return null;
                    })
                    .catch(reject);
            };

            renderBatch(0);
        });
    }

    function ensureCardsRendered(container, state, type, token) {
        if (state.cardsRendered[type]) {
            return Promise.resolve();
        }
        // While a status bucket is on its way the plan list holds its skeleton; see pickStatus().
        if (type === 'plan' && state.statusLoading) {
            return Promise.resolve();
        }

        const cards = type === 'plan' ? state.rawDataset.plancards : state.rawDataset.competencycards;
        return renderCardListIncremental(container, type, cards, token).then(() => {
            if (token === container.dataset.renderToken) {
                state.cardsRendered[type] = true;
            }
            return null;
        });
    }

    /**
     * Render or remove ghost cards that invite the user to load all items.
     * One ghost card per card type (plan/competency), each with its own count.
     */
    function renderGhostCards(container, state, labels) {
        // Remove any existing ghost cards.
        container.querySelectorAll('.dims-ghost-card').forEach(el => el.remove());

        if (!state.favouritesEnabled) {
            return;
        }

        // Ghost card for plans — show when fav filter is active and there are hidden items.
        if (state.favouriteFilterActive.plan) {
            let planRemaining;
            if (!state.fullDatasetLoaded.plan && state.hasnonfavouriteplans) {
                // Phase-1: not all data loaded yet.
                planRemaining = state.totalplans - state.rawDataset.plancards.length;
            } else {
                // Full dataset loaded: count non-favourite plans hidden by fav filter.
                planRemaining = state.totalplans - state.favouriteCountPlan;
            }
            if (planRemaining > 0) {
                const planList = container.querySelector('[data-cards-type="plan"]');
                if (planList) {
                    appendGhostCardTo(planList, planRemaining, labels, 'plan');
                }
            }
        }

        // Ghost card for competencies — same logic.
        if (state.favouriteFilterActive.competency) {
            let compRemaining;
            if (!state.fullDatasetLoaded.competency && state.hasnonfavouritecompetencies) {
                compRemaining = state.totalcompetencies - state.rawDataset.competencycards.length;
            } else {
                compRemaining = state.totalcompetencies - state.favouriteCountCompetency;
            }
            if (compRemaining > 0) {
                const compList = container.querySelector('[data-cards-type="competency"]');
                if (compList) {
                    appendGhostCardTo(compList, compRemaining, labels, 'competency');
                }
            }
        }
    }

    function appendGhostCardTo(list, remainingCount, labels, type) {
        const ghostLabel = labelWithCount(labels.ghostcardtitle, remainingCount);
        const li = document.createElement('li');
        li.className = 'col-12 col-sm-6 col-lg-4 mb-3 dims-card-item dims-ghost-card';
        li.dataset.ghostType = type;
        li.innerHTML = '<button type="button" class="dims-ghost-card-inner" aria-label="'
            + escapeHtml(ghostLabel) + '">'
            + '<span class="dims-ghost-icon-circle" aria-hidden="true">'
            + '<i class="fa fa-plus"></i>'
            + '</span>'
            + '<span class="dims-ghost-card-title">'
            + escapeHtml(ghostLabel)
            + '</span>'
            + '<span class="dims-ghost-card-subtitle">'
            + escapeHtml(labels.ghostcardsubtitle || '')
            + '</span>'
            + '</button>';
        list.appendChild(li);
    }

    function applyVisibility(container, type, filteredCards) {
        const list = container.querySelector('[data-cards-type="' + type + '"]');
        if (!list) {
            return;
        }

        const visibleIds = {};
        filteredCards.forEach((card) => {
            visibleIds[String(card.id)] = true;
        });

        list.querySelectorAll('.dims-card-item:not(.dims-ghost-card):not(.dims-skeleton-card)').forEach((item) => {
            const visible = !!visibleIds[item.dataset.cardId || ''];
            item.classList.toggle('dims-card-hidden', !visible);
            item.style.display = visible ? '' : 'none';
        });
    }

    function updateEmptyState(container, state, labels) {
        const empty = container.querySelector('.dims-empty-state');
        if (!empty) {
            return;
        }

        empty.style.display = 'none';

        // Nothing is known before the session's first dataset arrives, and a bucket still loading
        // has no result yet: the loading line, and a bucket's skeleton, stand in until then.
        if (!state.datasetReady || state.statusLoading) {
            return;
        }

        if (state.planStatus === 'active' && !state.rawDataset.hasactiveplans) {
            empty.textContent = labels.noactiveplans;
            empty.style.display = '';
            return;
        }

        if (!state.filteredDataset.plancards.length && !state.filteredDataset.competencycards.length) {
            // Don't show "no items" if ghost card is visible (there are more items to load).
            const ghostCard = container.querySelector('.dims-ghost-card');
            if (ghostCard && ghostCard.style.display !== 'none') {
                return;
            }
            // When the search or a filter hides every card, the cards exist: say no result matched.
            const hasCards = state.rawDataset.plancards.length > 0 || state.rawDataset.competencycards.length > 0;
            empty.textContent = hasCards ? labels.resultsnonefound : labels.nocompetencies;
            empty.style.display = '';
        }
    }

    /**
     * Show, word or hide the loading line from what is still on its way.
     *
     * Two kinds of load share the line: group loads, counted in state.groupLoads, and the status
     * bucket being fetched, state.statusLoading. Either can end while the other is pending, so the
     * line is derived from both whenever either changes, never switched off by the one that ended.
     * A group load in flight shows the page's own text, which is true of everything pending; a
     * bucket loading alone names the plans it is loading.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function syncLoadingLine(container, state, labels) {
        const loading = container.querySelector('.dims-loading-state');
        if (!loading) {
            return;
        }
        if (typeof loading.dataset.defaultText === 'undefined') {
            loading.dataset.defaultText = loading.textContent;
        }

        let message = null;
        if (state.groupLoads > 0) {
            message = loading.dataset.defaultText;
        } else if (state.statusLoading) {
            message = (state.statusLoading === 'complete' ? labels.statusloadingcomplete : labels.statusloadingreview)
                || loading.dataset.defaultText;
        }

        // The line is a polite live region: rewriting the same text would announce it again.
        const text = message === null ? loading.dataset.defaultText : message;
        if (loading.textContent !== text) {
            loading.textContent = text;
        }
        loading.style.display = message === null ? 'none' : '';
    }

    /**
     * Count a group load in on the loading line.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function startGroupLoad(container, state, labels) {
        state.groupLoads++;
        syncLoadingLine(container, state, labels);
    }

    /**
     * Count a group load out, once per load, and only for a response of the current session:
     * resetSession() puts the count back to zero, so a load of the previous session must not
     * take one off the count of the new one.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function endGroupLoad(container, state, labels) {
        state.groupLoads--;
        syncLoadingLine(container, state, labels);
    }

    function clearError(container) {
        const errorBox = container.querySelector('.dims-error-message');
        if (errorBox) {
            errorBox.style.display = 'none';
        }
    }

    function showError(container, message) {
        const errorBox = container.querySelector('.dims-error-message');
        if (!errorBox) {
            return;
        }
        const text = errorBox.querySelector('.dims-error-text');
        // For an element with role="alert" that lives in the DOM but is hidden
        // via display:none, several screen readers do not announce content
        // changes until the element becomes visible. Order matters here
        // (WCAG 4.1.3): clear the text, make the element visible, then write
        // the message in the next frame so the alert role fires reliably.
        if (text) {
            text.textContent = '';
        }
        errorBox.style.display = '';
        if (text) {
            requestAnimationFrame(function() {
                text.textContent = message;
            });
        }
    }

    /**
     * Update the counts on the favourites and show-all pills in place, without rebuilding the
     * filter bar, so toggleFavourite keeps focus on the star button (WCAG 2.4.3).
     */
    function updateFavouritePillCounts(container, state) {
        ['plan', 'competency'].forEach(function(type) {
            const favCount = type === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
            const total = type === 'plan' ? state.totalplans : state.totalcompetencies;

            const favBtn = container.querySelector('.dims-fav-filter-btn[data-fav-filter-type="' + type + '"]');
            if (favBtn) {
                const span = favBtn.querySelector('.dims-filter-count');
                if (span) {
                    span.textContent = String(favCount);
                }
            }

            const allBtn = container.querySelector('.dims-all-filter-btn[data-all-filter-type="' + type + '"]');
            if (allBtn) {
                const span = allBtn.querySelector('.dims-filter-count');
                if (span) {
                    span.textContent = String(total);
                }
            }
        });
    }

    /**
     * The pending results announcement of each block, keyed by its container, so that two blocks
     * on one page do not cancel each other's.
     */
    const resultsAnnounceTimers = new WeakMap();

    /**
     * Announce the filtered results count to assistive technology via the
     * aria-live region in summary.mustache (WCAG 4.1.3). Debounced so rapid
     * input (typing in search) doesn't spam the screen reader.
     */
    function announceResults(container, state, labels) {
        const region = container.querySelector('[data-results-status]');
        if (!region) {
            return;
        }
        clearTimeout(resultsAnnounceTimers.get(container));
        resultsAnnounceTimers.set(container, setTimeout(function() {
            // Nothing is counted before the first dataset arrives, and the grid of a bucket still
            // loading counts no plan: the render that follows the response announces the real count.
            if (!state.datasetReady || state.statusLoading) {
                return;
            }
            const planCount = state.filteredDataset.plancards.length;
            const compCount = state.filteredDataset.competencycards.length;
            let message;
            if (planCount === 0 && compCount === 0) {
                message = labels.resultsnonefound || '';
            } else {
                message = (labels.resultsfound || '')
                    .replace('{$a->plans}', String(planCount))
                    .replace('{$a->competencies}', String(compCount));
            }
            // Clear then set on next frame so identical messages still fire.
            region.textContent = '';
            requestAnimationFrame(function() {
                region.textContent = message;
            });
        }, 600));
    }

    /**
     * Announce a transient favourite-toggle error via the assertive aria-live
     * region (WCAG 3.3.1, 4.1.3). Auto-clears after 5s so the message doesn't
     * linger in the AT cursor.
     */
    function announceFavouriteError(container, message) {
        const region = container.querySelector('[data-fav-status]');
        if (!region) {
            return;
        }
        region.textContent = '';
        requestAnimationFrame(function() {
            region.textContent = message;
        });
        setTimeout(function() {
            if (region.textContent === message) {
                region.textContent = '';
            }
        }, 5000);
    }

    /**
     * Escape a data attribute value for a double-quoted attribute selector.
     *
     * Tag filter values are the options of an admin-defined custom field, so they can hold a quote
     * or a backslash, which would make querySelector() throw.
     *
     * @param {string} value Attribute value as read from the dataset.
     * @return {string}
     */
    const selectorValue = (value) => CSS.escape(String(value));

    /**
     * Capture an identifier for the currently focused element inside the block
     * so focus can be restored after a DOM rebuild (WCAG 2.4.3).
     * @return {Object|null}
     */
    function captureFocusKey(container) {
        const active = document.activeElement;
        if (!active || !container.contains(active)) {
            return null;
        }
        if (active.matches('.dims-status-filter-btn[data-status-filter]')) {
            /* Focus goes to the pill the rebuilt group checks, the group's one tab stop, rather
               than to the pill that had it: that one stays unchecked when it was clicked while
               another bucket was loading, or when its bucket failed to load. */
            return {selector: '.dims-status-filter-btn[aria-checked="true"]'};
        }
        if (active.matches('.dims-fav-filter-btn[data-fav-filter-type]')) {
            return {selector: '.dims-fav-filter-btn[data-fav-filter-type="' + selectorValue(active.dataset.favFilterType) + '"]'};
        }
        if (active.matches('.dims-all-filter-btn[data-all-filter-type]')) {
            return {selector: '.dims-all-filter-btn[data-all-filter-type="' + selectorValue(active.dataset.allFilterType) + '"]'};
        }
        if (active.matches('.dims-filter-tab[data-filter-field]')) {
            const field = selectorValue(active.dataset.filterField);
            const value = selectorValue(active.dataset.filterValue || '');
            return {selector: '.dims-filter-tab[data-filter-field="' + field + '"][data-filter-value="' + value + '"]'};
        }
        if (active.matches('.dims-clear-filters-btn[data-clear-type]')) {
            return {selector: '.dims-clear-filters-btn[data-clear-type="' + selectorValue(active.dataset.clearType) + '"]'};
        }
        if (active.matches('.dims-filter-select[data-filter-field]')) {
            return {selector: '.dims-filter-select[data-filter-field="' + selectorValue(active.dataset.filterField) + '"]'};
        }
        return null;
    }

    function restoreFocus(container, focusKey, fallbackSelector) {
        if (!focusKey) {
            return;
        }
        const target = container.querySelector(focusKey.selector);
        if (target) {
            target.focus({preventScroll: true});
            return;
        }
        if (fallbackSelector) {
            const fallback = container.querySelector(fallbackSelector);
            if (fallback) {
                fallback.focus({preventScroll: true});
            }
        }
    }

    /**
     * Toggle favourite state for a card via AJAX.
     *
     * @param {HTMLElement} btn The favourite button that was clicked.
     * @param {HTMLElement} container Block container element.
     * @param {Object} state Application state.
     * @param {Object} options Init options with labels.
     */
    function toggleFavourite(btn, container, state, options) {
        const itemtype = btn.dataset.favType;
        const itemid = parseInt(btn.dataset.favId, 10);

        if (!itemtype || isNaN(itemid)) {
            return;
        }

        // Prevent double-click.
        if (btn.dataset.favPending) {
            return;
        }
        btn.dataset.favPending = '1';

        Ajax.call([{
            methodname: 'block_dimensions_toggle_favourite',
            args: {itemtype: itemtype, itemid: itemid}
        }])[0].then(function(result) {
            delete btn.dataset.favPending;

            const nowFav = !!result.isfavourite;

            // Update the icon.
            const icon = btn.querySelector('i');
            if (icon) {
                if (nowFav) {
                    icon.className = 'fa fa-star dims-fav-icon-filled';
                } else {
                    icon.className = 'fa fa-star-o dims-fav-icon-empty';
                }
            }

            // Update aria-label, aria-pressed, and title.
            const label = nowFav ? options.labels.removefromfavourites : options.labels.addtofavourites;
            btn.setAttribute('aria-label', label);
            btn.setAttribute('aria-pressed', nowFav ? 'true' : 'false');
            btn.setAttribute('title', label);

            // Update the card data too: a card redrawn from it later (a bucket round trip) must carry the
            // label that matches its state (WCAG 4.1.2).
            const cards = itemtype === 'plan' ? state.rawDataset.plancards : state.rawDataset.competencycards;
            for (let i = 0; i < cards.length; i++) {
                if (cards[i].id === itemid) {
                    cards[i].isfavourite = nowFav;
                    cards[i].favouritearialabel = label;
                    cards[i].favouritetitle = label;
                    break;
                }
            }

            // The fav/all pills only need a full filter-bar rebuild when the
            // count crosses the 0 boundary (pills appear or disappear). For any
            // other transition we update counts in place to preserve focus on
            // the star button the user just activated (WCAG 2.4.3).
            const prevFavPlan = state.favouriteCountPlan;
            const prevFavComp = state.favouriteCountCompetency;
            updateFavouriteCounts(state);
            const pillsToggleVisibility =
                (prevFavPlan === 0) !== (state.favouriteCountPlan === 0) ||
                (prevFavComp === 0) !== (state.favouriteCountCompetency === 0);
            if (pillsToggleVisibility) {
                state.filtersRendered = false;
            } else {
                updateFavouritePillCounts(container, state);
            }

            // If favourite filter is active and count dropped to 0, deactivate and load all.
            const typeFavCount = itemtype === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
            const typeHasNonFavs = itemtype === 'plan' ? state.hasnonfavouriteplans : state.hasnonfavouritecompetencies;
            if (state.favouriteFilterActive[itemtype] && typeFavCount === 0) {
                state.favouriteFilterActive[itemtype] = false;
                if (!state.fullDatasetLoaded[itemtype] && typeHasNonFavs) {
                    loadGroupDataset(container, state, options, itemtype);
                    return null;
                }
            }

            // If favourite filter is active, re-apply visibility.
            if (state.favouriteFilterActive[itemtype]) {
                applyFilters(state);
                applyVisibility(container, itemtype,
                    itemtype === 'plan' ? state.filteredDataset.plancards : state.filteredDataset.competencycards);
                updateEmptyState(container, state, options.labels);
            }

            rerender(container, state, options);
            return null;
        }).catch(function() {
            delete btn.dataset.favPending;
            announceFavouriteError(container, options.labels.favouriteerror);
        });
    }

    function rerender(container, state, options) {
        applyFilters(state);

        // If the filter bar is about to be rebuilt, capture the focused
        // element so we can restore focus after innerHTML replacement
        // (WCAG 2.4.3 — Focus Order).
        const focusKey = !state.filtersRendered ? captureFocusKey(container) : null;

        if (!state.filtersRendered) {
            renderFilterControls(container, 'plan', state.rawDataset.plancards, state, options.labels);
            renderFilterControls(container, 'competency', state.rawDataset.competencycards, state, options.labels);
            state.filtersRendered = true;
        }

        syncFilterActiveState(container, state);
        updateClearFilterButtons(container, state);

        if (focusKey) {
            restoreFocus(container, focusKey, '.dims-block-search-input');
        }

        const token = String(++state.renderToken);
        container.dataset.renderToken = token;

        Promise.all([
            ensureCardsRendered(container, state, 'plan', token),
            ensureCardsRendered(container, state, 'competency', token)
        ]).then(() => {
            // A later render, or a new session, has taken the lists over and finishes them itself.
            if (token !== container.dataset.renderToken) {
                return null;
            }
            applyVisibility(container, 'plan', state.filteredDataset.plancards);
            applyVisibility(container, 'competency', state.filteredDataset.competencycards);
            renderGhostCards(container, state, options.labels);
            updateEmptyState(container, state, options.labels);

            // Show section headers based on raw dataset (not filtered).
            // Headers stay visible even when filters yield zero cards.
            container.querySelectorAll('.dims-section-header').forEach(function(header) {
                var sectionType = header.dataset.sectionHeader;
                var hasCards = sectionType === 'plan'
                    ? state.rawDataset.hasplancards
                    : state.rawDataset.hascompetencies;
                header.style.display = hasCards ? '' : 'none';
            });

            // Announce filtered results count via aria-live (WCAG 4.1.3).
            announceResults(container, state, options.labels);
            return null;
        }).catch(() => {
            showError(container, options.labels.loaderror);
        });
    }

    /**
     * Draw placeholder cards while a status bucket is on its way.
     *
     * The grid keeps its shape and the learner sees where the cards will land, instead of an
     * empty block that looks broken. It draws as many cards as the bucket's pill counts, up to three.
     * Call it once state.statusLoading names the bucket: the loading line is worded from it.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function renderStatusSkeleton(container, state, labels) {
        const list = container.querySelector('[data-cards-type="plan"]');
        const wanted = Math.min(3, Math.max(1, state.planCounts[state.planStatus] || 1));
        const items = [];

        for (let i = 0; i < wanted; i++) {
            items.push('<li class="col-12 col-sm-6 col-lg-4 mb-3 dims-card-item dims-skeleton-card" aria-hidden="true">'
                + '<div class="dims-skeleton-media"></div>'
                + '<div class="dims-skeleton-body">'
                + '<span class="dims-skeleton-line dims-skeleton-line-sm"></span>'
                + '<span class="dims-skeleton-line"></span>'
                + '<span class="dims-skeleton-line dims-skeleton-line-md"></span>'
                + '</div></li>');
        }

        if (list) {
            list.innerHTML = items.join('');
            list.setAttribute('aria-busy', 'true');
        }

        syncLoadingLine(container, state, labels);
    }

    /**
     * Clear the grid's busy flag and bring the loading line back in step, once state.statusLoading
     * no longer names a bucket.
     *
     * The skeleton cards stay until the plan list is next rendered, which empties it first. The
     * loading line stays up while a group load is still pending; see syncLoadingLine().
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} labels Localized labels.
     */
    function clearStatusSkeleton(container, state, labels) {
        const list = container.querySelector('[data-cards-type="plan"]');

        if (list) {
            list.removeAttribute('aria-busy');
        }
        syncLoadingLine(container, state, labels);
    }

    /**
     * Put a status bucket's kept plan cards back in the dataset the grid renders from.
     *
     * The active bucket can still hold only the favourite plans of the favourites-only first
     * request. Such a list is shown under the favourites pill, whose ghost card offers the rest:
     * under "Show all" it would sit beside a count the grid does not hold, with nothing to load
     * the missing plans.
     *
     * @param {Object} state Application state.
     * @param {string} bucket 'active', 'review' or 'complete'.
     */
    function showBucketCards(state, bucket) {
        state.rawDataset.plancards = state.statusCards[bucket] || [];
        state.rawDataset.hasplancards = state.rawDataset.plancards.length > 0;
        updateFavouriteCounts(state);
        state.favouriteFilterActive.plan = bucket === 'active'
            && state.favouriteCountPlan > 0
            && !state.fullDatasetLoaded.plan
            && state.hasnonfavouriteplans;
    }

    /**
     * Switch the plan grid to another status bucket, fetching it the first time.
     *
     * Only the bucket the block opens on arrives with the first request; every other bucket is
     * built when it is asked for and kept afterwards, so a second visit costs nothing.
     * Competency cards are untouched: they are about work in progress and belong to the active
     * plans alone. When the fetch fails the grid goes back to the bucket it left, with the plan
     * filters it had, so the failed bucket can be picked again.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} options Init options.
     * @param {string} bucket 'active', 'review' or 'complete'.
     * @return {Promise}
     */
    function pickStatus(container, state, options, bucket) {
        if (!bucket || bucket === state.planStatus || state.statusLoading) {
            return Promise.resolve();
        }

        // Leaving a bucket keeps its cards, so coming back needs no request. Its plan filters are
        // kept only for the failure path, which returns to it as it was left.
        const previous = state.planStatus;
        const previousFilters = {
            favourite: state.favouriteFilterActive.plan,
            tag1: state.activeFilters.plan_tag1,
            tag2: state.activeFilters.plan_tag2
        };
        state.statusCards[previous] = state.rawDataset.plancards;
        state.planStatus = bucket;
        state.favouriteFilterActive.plan = false;
        /* eslint-disable camelcase */
        state.activeFilters.plan_tag1 = '';
        state.activeFilters.plan_tag2 = '';
        /* eslint-enable camelcase */
        state.filtersRendered = false;
        state.cardsRendered.plan = false;
        clearError(container);

        if (state.statusCards[bucket]) {
            showBucketCards(state, bucket);
            rerender(container, state, options);
            return Promise.resolve();
        }

        /* Until the response lands, nothing may show the cards of the bucket being left under the
           new pill: any render in between (a competency load, a search) would otherwise draw them
           over the skeleton, and the rebuilt bar would offer their tag values. They are kept in
           statusCards, where the failure path finds them. */
        state.statusLoading = bucket;
        state.rawDataset.plancards = [];
        renderStatusSkeleton(container, state, options.labels);
        rerender(container, state, options);

        const session = state.session;
        return fetchDataset(options.endpointmethod, {
            favouritesonly: false,
            loadgroup: 'plan',
            planstatus: bucket
        }).then((dataset) => {
            if (session !== state.session) {
                return null;
            }
            state.statusLoading = null;
            clearStatusSkeleton(container, state, options.labels);
            state.statusCards[bucket] = dataset.plancards || [];
            state.rawDataset.hasactiveplans = !!dataset.hasactiveplans;
            if (dataset.plancounts) {
                state.planCounts = dataset.plancounts;
            }
            if (bucket === 'active') {
                // Fetched without the favourites-only cut, so this is the whole active list.
                state.totalplans = dataset.totalplans || 0;
                state.hasnonfavouriteplans = false;
                state.fullDatasetLoaded.plan = true;
            }
            showBucketCards(state, bucket);
            state.filtersRendered = false;
            state.cardsRendered.plan = false;
            rerender(container, state, options);
            return null;
        }).catch(() => {
            if (session !== state.session) {
                return;
            }
            state.statusLoading = null;
            clearStatusSkeleton(container, state, options.labels);
            state.planStatus = previous;
            showBucketCards(state, previous);
            // The same cards as before the switch, so the learner's filters over them still hold.
            state.favouriteFilterActive.plan = previousFilters.favourite;
            /* eslint-disable camelcase */
            state.activeFilters.plan_tag1 = previousFilters.tag1;
            state.activeFilters.plan_tag2 = previousFilters.tag2;
            /* eslint-enable camelcase */
            state.filtersRendered = false;
            state.cardsRendered.plan = false;
            rerender(container, state, options);
            showError(container, options.labels.loaderror);
        });
    }

    /**
     * The loadgroup value of a request for the plan cards, the competency cards, or both.
     *
     * @param {boolean} plans Whether the plan cards are wanted.
     * @param {boolean} competencies Whether the competency cards are wanted.
     * @return {string} 'plan', 'competency', or '' for both.
     */
    function loadGroupFor(plans, competencies) {
        if (!plans) {
            return 'competency';
        }
        return competencies ? '' : 'plan';
    }

    /**
     * Load the rest of a card type that the favourites-only first request left out (Phase 2).
     *
     * Only a group whose list is not yet whole is fetched. Both groups are fetched from the active
     * bucket: competency cards are built from the active plans alone, and every other bucket
     * arrives with all its plan cards, so only the active plan list can be missing some. Plan
     * cards that arrive while another bucket is on screen are kept for the active bucket and leave
     * the grid alone.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} options Init options.
     * @param {string} group 'plan', 'competency', or '' for both.
     * @return {Promise}
     */
    function loadGroupDataset(container, state, options, group) {
        const loadPlans = group !== 'competency' && !state.fullDatasetLoaded.plan;
        const loadCompetencies = group !== 'plan' && !state.fullDatasetLoaded.competency;
        if (!loadPlans && !loadCompetencies) {
            return Promise.resolve();
        }

        startGroupLoad(container, state, options.labels);

        /* The bucket is always named: leaving it out asks the server to pick the bucket to open on
           again, and a competency request for any other bucket comes back empty. */
        const session = state.session;
        return fetchDataset(options.endpointmethod, {
            favouritesonly: false,
            loadgroup: loadGroupFor(loadPlans, loadCompetencies),
            planstatus: 'active'
        })
            .then((dataset) => {
                if (session !== state.session) {
                    return null;
                }
                state.rawDataset.hasactiveplans = !!dataset.hasactiveplans;

                if (dataset.filtersettings) {
                    state.filterSettings = dataset.filtersettings;
                }

                if (typeof dataset.favouritesenabled !== 'undefined') {
                    state.favouritesEnabled = !!dataset.favouritesenabled;
                }

                if (dataset.plancounts) {
                    state.planCounts = dataset.plancounts;
                }

                // Merge: only update the group(s) that were loaded.
                if (loadPlans) {
                    state.statusCards.active = dataset.plancards || [];
                    state.totalplans = dataset.totalplans || state.totalplans;
                    state.hasnonfavouriteplans = false;
                    state.fullDatasetLoaded.plan = true;
                    if (state.planStatus === 'active') {
                        state.rawDataset.plancards = state.statusCards.active;
                        state.rawDataset.hasplancards = !!dataset.hasplancards;
                        state.cardsRendered.plan = false;
                    }
                }
                if (loadCompetencies) {
                    state.rawDataset.competencycards = dataset.competencycards || [];
                    state.rawDataset.hascompetencies = !!dataset.hascompetencies;
                    state.totalcompetencies = dataset.totalcompetencies || state.totalcompetencies;
                    state.hasnonfavouritecompetencies = false;
                    state.fullDatasetLoaded.competency = true;
                    state.cardsRendered.competency = false;
                }

                updateFavouriteCounts(state);
                state.filtersRendered = false;
                rerender(container, state, options);
                endGroupLoad(container, state, options.labels);
                return null;
            })
            .catch(() => {
                if (session !== state.session) {
                    return;
                }
                endGroupLoad(container, state, options.labels);
                showError(container, options.labels.loaderror);
            });
    }

    /**
     * Trigger dataset load for a group and switch to showing all items.
     * @param {string|null} type If null, deactivate fav filter for all types.
     */
    function showAllItems(container, state, options, type) {
        if (type) {
            state.favouriteFilterActive[type] = false;
        } else {
            state.favouriteFilterActive.plan = false;
            state.favouriteFilterActive.competency = false;
        }

        state.filtersRendered = false;

        // Determine which group(s) still need loading.
        const groupToLoad = type || '';
        let needsLoad = false;
        if (groupToLoad === '' || groupToLoad === 'plan') {
            needsLoad = needsLoad || (!state.fullDatasetLoaded.plan && state.hasnonfavouriteplans);
        }
        if (groupToLoad === '' || groupToLoad === 'competency') {
            needsLoad = needsLoad || (!state.fullDatasetLoaded.competency && state.hasnonfavouritecompetencies);
        }

        if (!needsLoad) {
            rerender(container, state, options);
            return;
        }

        loadGroupDataset(container, state, options, groupToLoad);
    }

    function bindEvents(container, state, options) {
        const searchInput = container.querySelector('.dims-block-search-input');
        const clearButton = container.querySelector('.dims-block-search-clear');
        let debounceTimer = null;

        // A value the browser puts back in the input fires no input event; loadData() reads it.
        if (searchInput && clearButton) {
            clearButton.style.display = searchInput.value.length ? 'flex' : 'none';
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (clearButton) {
                    clearButton.style.display = searchInput.value.length ? 'flex' : 'none';
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    state.searchTerm = searchInput.value.trim();
                    state.normalizedSearch = normalizeText(state.searchTerm);

                    // Search always deactivates favourite filter to search across all items.
                    if (state.normalizedSearch) {
                        state.favouriteFilterActive.plan = false;
                        state.favouriteFilterActive.competency = false;
                        // Reset all tag filters so search shows everything.
                        // Keys are the snake_case data-filter-field contract.
                        /* eslint-disable camelcase */
                        state.activeFilters.plan_tag1 = '';
                        state.activeFilters.plan_tag2 = '';
                        state.activeFilters.competency_tag1 = '';
                        state.activeFilters.competency_tag2 = '';
                        /* eslint-enable camelcase */
                        state.filtersRendered = false;
                    }

                    /* The search reaches the plan bucket on screen and every competency card, so
                       fetch first whatever of those the favourites-only first request left out.
                       Only the active bucket can be missing plans; the competency cards can be
                       missing some whichever bucket is on screen. */
                    const needPlans = state.planStatus === 'active' && !state.fullDatasetLoaded.plan
                        && state.hasnonfavouriteplans;
                    const needCompetencies = !state.fullDatasetLoaded.competency && state.hasnonfavouritecompetencies;
                    if (state.normalizedSearch && (needPlans || needCompetencies)) {
                        const group = loadGroupFor(needPlans, needCompetencies);
                        return loadGroupDataset(container, state, options, group).then(() => {
                            state.searchTerm = searchInput.value.trim();
                            state.normalizedSearch = normalizeText(state.searchTerm);
                            rerender(container, state, options);
                            return null;
                        });
                    }
                    rerender(container, state, options);
                    return null;
                }, 120);
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                clearButton.style.display = 'none';
                state.searchTerm = '';
                state.normalizedSearch = '';
                rerender(container, state, options);
            });
        }

        // One delegated click handler for the cards and the filter bar; its branch count is over
        // eslint's complexity limit.
        // eslint-disable-next-line complexity
        container.addEventListener('click', (e) => {
            // Trail items are real links, so the keyboard reaches and follows them natively
            // (WCAG 2.1.1). A link carrying a plan id is intercepted only to call the
            // set_return_context web service before navigating.
            const trailLink = e.target.closest('a.trail-item-link');
            if (trailLink) {
                const planid = parseInt(trailLink.dataset.trailPlanid, 10);
                if (planid) {
                    e.preventDefault();
                    e.stopPropagation();
                    const href = trailLink.href;
                    let navigated = false;
                    const navigate = () => {
                        if (!navigated) {
                            navigated = true;
                            window.location.href = href;
                        }
                    };
                    Ajax.call([{
                        methodname: 'block_dimensions_set_return_context',
                        args: {planid: planid, courseid: 0}
                    }])[0].then(navigate).catch(navigate);
                    // Fallback: navigate after 1.5s even if WS is slow.
                    setTimeout(navigate, 1500);
                }
                // Otherwise let the browser navigate the link natively.
                return;
            }

            // Handle a status pill click: swap the plan grid to that bucket.
            const statusBtn = e.target.closest('.dims-status-filter-btn');
            if (statusBtn) {
                e.preventDefault();
                pickStatus(container, state, options, statusBtn.dataset.statusFilter);
                return;
            }

            // Handle "Clear filters" button click (scoped to one section).
            const clearBtn = e.target.closest('.dims-clear-filters-btn');
            if (clearBtn) {
                e.preventDefault();
                const clearType = clearBtn.dataset.clearType;
                state.activeFilters[clearType + '_tag1'] = '';
                state.activeFilters[clearType + '_tag2'] = '';
                state.favouriteFilterActive[clearType] = false;
                state.filtersRendered = false;

                // If full dataset not loaded and needed, load it.
                const typeHasNonFavs = clearType === 'plan'
                    ? state.hasnonfavouriteplans && state.planStatus === 'active'
                    : state.hasnonfavouritecompetencies;
                if (!state.fullDatasetLoaded[clearType] && typeHasNonFavs) {
                    loadGroupDataset(container, state, options, clearType);
                    return;
                }
                rerender(container, state, options);
                return;
            }

            // Handle favourite toggle button clicks.
            const favBtn = e.target.closest('.dims-fav-btn');
            if (favBtn && state.favouritesEnabled) {
                e.preventDefault();
                e.stopPropagation();
                toggleFavourite(favBtn, container, state, options);
                return;
            }

            // Handle ghost card click — load all items for that block's type.
            const ghostCard = e.target.closest('.dims-ghost-card');
            if (ghostCard) {
                e.preventDefault();
                const ghostType = ghostCard.dataset.ghostType || null;
                showAllItems(container, state, options, ghostType);
                return;
            }

            // Handle a "Show all" pill click.
            const allFilterBtn = e.target.closest('.dims-all-filter-btn');
            if (allFilterBtn) {
                e.preventDefault();
                const allType = allFilterBtn.dataset.allFilterType;
                showAllItems(container, state, options, allType);
                return;
            }

            // Handle favourite filter button clicks.
            const favFilterBtn = e.target.closest('.dims-fav-filter-btn');
            if (favFilterBtn) {
                e.preventDefault();
                const favType = favFilterBtn.dataset.favFilterType;
                state.favouriteFilterActive[favType] = !state.favouriteFilterActive[favType];

                const favTypeHasNonFavs = favType === 'plan'
                    ? state.hasnonfavouriteplans : state.hasnonfavouritecompetencies;
                if (!state.favouriteFilterActive[favType] && !state.fullDatasetLoaded[favType] && favTypeHasNonFavs) {
                    // Deactivating favourite filter: need this group's dataset.
                    showAllItems(container, state, options, favType);
                    return;
                }

                syncFilterActiveState(container, state);
                applyFilters(state);
                applyVisibility(container, favType,
                    favType === 'plan' ? state.filteredDataset.plancards : state.filteredDataset.competencycards);
                updateClearFilterButtons(container, state);
                renderGhostCards(container, state, options.labels);
                updateEmptyState(container, state, options.labels);
                return;
            }

            // Handle tag filter tab clicks.
            const tab = e.target.closest('.dims-filter-tab');
            if (!tab || tab.classList.contains('dims-fav-filter-btn') || tab.classList.contains('dims-all-filter-btn')
                    || tab.classList.contains('dims-status-filter-btn')) {
                return;
            }

            state.activeFilters[tab.dataset.filterField] = tab.dataset.filterValue || '';
            rerender(container, state, options);
        });

        container.addEventListener('change', (e) => {
            const select = e.target.closest('.dims-filter-select');
            if (!select) {
                return;
            }

            state.activeFilters[select.dataset.filterField] = select.value || '';
            rerender(container, state, options);
        });

        const retryButton = container.querySelector('.dims-error-retry');
        if (retryButton) {
            retryButton.addEventListener('click', () => {
                // The retry hides the error box the button sits in, which would drop focus to the
                // document body (WCAG 2.4.3).
                if (document.activeElement === retryButton) {
                    focusBlock(container);
                }
                loadData(container, state, options);
            });
        }

        // Filter toggle button (mobile only — desktop keeps filters always visible via CSS).
        const toggleBtn = container.querySelector('.dims-filter-toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isOpen = container.classList.toggle('dims-filters-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            // On resize to desktop width: collapse the panel and reset button state.
            const mq = globalThis.matchMedia('(max-width: 575.98px)');
            mq.addEventListener('change', function(e) {
                if (!e.matches) {
                    container.classList.remove('dims-filters-open');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    /**
     * Move focus to the block itself, for as long as it holds it.
     *
     * For a focused control that is about to be hidden. The block is a named region, so a screen
     * reader says where focus went. The tabindex that lets it take focus goes as soon as focus
     * leaves it, so the block never becomes a tab stop of its own.
     *
     * @param {HTMLElement} container Block container.
     */
    function focusBlock(container) {
        if (!container.hasAttribute('tabindex')) {
            container.setAttribute('tabindex', '-1');
            container.addEventListener('blur', () => container.removeAttribute('tabindex'), {once: true});
        }
        container.focus({preventScroll: true});
    }

    function resetRenderedState(container, state) {
        state.filtersRendered = false;
        state.cardsRendered.plan = false;
        state.cardsRendered.competency = false;

        const planList = container.querySelector('[data-cards-type="plan"]');
        if (planList) {
            planList.innerHTML = '';
        }
        const competencyList = container.querySelector('[data-cards-type="competency"]');
        if (competencyList) {
            competencyList.innerHTML = '';
        }
    }

    /**
     * Put the block's markup back to the shell the page served, for a new session.
     *
     * Bumping the render token stops a card list still being rendered in batches. The filter bars
     * go with the dataset they were built from, so nothing left on screen can start a request for
     * the session being replaced.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state, already reset by resetSession().
     * @param {Object} labels Localized labels.
     */
    function resetView(container, state, labels) {
        container.dataset.renderToken = String(++state.renderToken);
        container.querySelectorAll('.dims-filters-host').forEach((host) => {
            FilterTabsNav.destroyAll(host);
            host.innerHTML = '';
        });
        clearStatusSkeleton(container, state, labels);
        resetRenderedState(container, state);
        container.querySelectorAll('.dims-section-header, .dims-empty-state').forEach((element) => {
            element.style.display = 'none';
        });
    }

    /**
     * Fetch the dataset and render the block from it: on init, and again from the Retry button.
     *
     * Either way it starts from the state the block opens in (resetSession(), resetView()), so
     * every branch below fetches the groups it needs and renders both card lists. The search term
     * is read from the input, which may hold one on a retry or when the browser put a value back.
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} options Init options.
     * @return {Promise}
     */
    function loadData(container, state, options) {
        resetSession(state);
        resetView(container, state, options.labels);
        clearError(container);

        const searchInput = container.querySelector('.dims-block-search-input');
        if (searchInput) {
            state.searchTerm = searchInput.value.trim();
            state.normalizedSearch = normalizeText(state.searchTerm);
        }

        // Phase 1: if favourites are enabled, load only favourites first.
        const useFavouritesFirst = state.favouritesEnabled;
        const fetchArgs = useFavouritesFirst ? {favouritesonly: true} : {};
        const session = state.session;
        startGroupLoad(container, state, options.labels);

        return fetchDataset(options.endpointmethod, fetchArgs)
            // The phase-1/phase-2 favourites branching is over eslint's complexity limit.
            // eslint-disable-next-line complexity
            .then((dataset) => {
                if (session !== state.session) {
                    return null;
                }
                state.rawDataset = {
                    hasactiveplans: !!dataset.hasactiveplans,
                    hasplancards: !!dataset.hasplancards,
                    hascompetencies: !!dataset.hascompetencies,
                    plancards: dataset.plancards || [],
                    competencycards: dataset.competencycards || []
                };
                state.datasetReady = true;
                // Both lists are rebuilt from this dataset, whatever a render drew while it was on
                // its way: a search typed meanwhile renders them empty and marks them done.
                resetRenderedState(container, state);

                if (dataset.filtersettings) {
                    state.filterSettings = dataset.filtersettings;
                }

                // Update favourites enabled from server response.
                if (typeof dataset.favouritesenabled !== 'undefined') {
                    state.favouritesEnabled = !!dataset.favouritesenabled;
                }

                // The status axis: every bucket's count rides this first response.
                if (dataset.plancounts) {
                    state.planCounts = dataset.plancounts;
                }
                state.planStatus = dataset.planstatus || 'active';
                state.statusCards[state.planStatus] = state.rawDataset.plancards;

                // Store totals and per-group non-favourite flags.
                state.totalplans = dataset.totalplans || 0;
                state.totalcompetencies = dataset.totalcompetencies || 0;
                state.hasnonfavouriteplans = !!dataset.hasnonfavouriteplans;
                state.hasnonfavouritecompetencies = !!dataset.hasnonfavouritecompetencies;
                updateFavouriteCounts(state);

                const hasAnyNonFavourites = state.hasnonfavouriteplans || state.hasnonfavouritecompetencies;
                const favCards = state.rawDataset.plancards.length + state.rawDataset.competencycards.length;

                // The group whose full list is fetched next ('plan', 'competency', '' for both), or
                // null when this dataset is all there is to show for now.
                let group = null;
                if (useFavouritesFirst && hasAnyNonFavourites && state.normalizedSearch) {
                    /* A search reaches every card, as in the search handler: the favourites filter
                       stays off, and whatever the favourites-only request left out of the plan
                       bucket on screen and of the competency cards is fetched. */
                    const needPlans = state.planStatus === 'active' && state.hasnonfavouriteplans;
                    const needCompetencies = state.hasnonfavouritecompetencies;
                    if (needPlans || needCompetencies) {
                        group = loadGroupFor(needPlans, needCompetencies);
                    }
                } else if (useFavouritesFirst && hasAnyNonFavourites && favCards > 0) {
                    // Check per-group: which groups have items but zero favourites.
                    const planHasItemsNoFavs = state.totalplans > 0 && state.favouriteCountPlan === 0;
                    const compHasItemsNoFavs = state.totalcompetencies > 0 && state.favouriteCountCompetency === 0;

                    // Pre-set fav filter for groups that HAVE favourites.
                    state.favouriteFilterActive.plan = state.favouriteCountPlan > 0 && state.hasnonfavouriteplans;
                    state.favouriteFilterActive.competency = state.favouriteCountCompetency > 0 && state.hasnonfavouritecompetencies;

                    if (planHasItemsNoFavs && compHasItemsNoFavs) {
                        // Neither group has a favourite — load everything.
                        group = '';
                    } else if (planHasItemsNoFavs) {
                        // Only plans need full load — keep competency favs in phase-1.
                        group = 'plan';
                    } else if (compHasItemsNoFavs) {
                        // Only competencies need full load — keep plan favs in phase-1.
                        group = 'competency';
                    }
                    // Otherwise both groups have some favourites: stay in phase-1 mode.
                } else if (useFavouritesFirst && hasAnyNonFavourites && favCards === 0) {
                    // No favourites exist — load full dataset immediately.
                    group = '';
                } else {
                    // Either favourites are disabled, no favourites exist, or
                    // all items are favourites — we already have everything.
                    state.fullDatasetLoaded.plan = true;
                    state.fullDatasetLoaded.competency = true;
                }

                let next = null;
                if (group === null) {
                    rerender(container, state, options);
                } else {
                    next = loadGroupDataset(container, state, options, group);
                }
                // The first request counts out last, so a fetch of the rest keeps the line up.
                endGroupLoad(container, state, options.labels);
                return next;
            })
            .catch(() => {
                if (session !== state.session) {
                    return;
                }
                endGroupLoad(container, state, options.labels);
                showError(container, options.labels.loaderror);
            });
    }

    function init(options) {
        const safeOptions = options || {};
        const container = document.getElementById(safeOptions.containerid);
        if (!container) {
            return;
        }

        const state = createState(safeOptions);
        bindEvents(container, state, safeOptions);
        loadData(container, state, safeOptions);
    }

    return {init: init};
});

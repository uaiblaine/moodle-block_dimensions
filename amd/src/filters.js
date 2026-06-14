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
 * Supports two-phase loading: favourites are loaded first for a fast initial
 * render, then the full dataset is fetched on demand (search, "All items" pill,
 * or ghost-card click).
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

    function renderFilterControls(container, type, cards, state, labels) {
        const host = container.querySelector('.dims-filters-host[data-filters-type="' + type + '"]');
        if (!host) {
            return;
        }

        const settings = state.filterSettings[type] || {};
        const html = [];
        let hasAnyFilter = false;
        // Group label used as accessible name for each radiogroup and as the
        // aria-label of the dropdown select. Better than the meaningless tag
        // slug ("tag1" / "tag2") that was previously exposed to AT (WCAG 2.4.6,
        // 4.1.2).
        const groupLabel = type === 'plan'
            ? (labels.filterbyplan || labels.filterall)
            : (labels.filterbycompetency || labels.filterall);

        html.push('<div class="dims-filters-bar" role="toolbar" aria-label="' + escapeHtml(groupLabel) + '">');

        // Favourite / All pills (always first, if enabled). Pills are mutually
        // exclusive within a type, so they form a radiogroup (WCAG 4.1.2). The
        // checked one carries tabindex="0", siblings tabindex="-1" so the
        // group is reached as a single tab stop and arrow keys navigate within
        // it (managed by filter_tabs_nav.js).
        // Counts are per-type: plan pills show plan counts, competency pills show competency counts.
        if (state.favouritesEnabled) {
            const typeFavCount = type === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
            const typeTotal = type === 'plan' ? state.totalplans : state.totalcompetencies;
            const isFavActive = state.favouriteFilterActive[type];

            if (typeFavCount > 0) {
                hasAnyFilter = true;
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
            }
        }

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
        container.querySelectorAll('.dims-filter-tab:not(.dims-fav-filter-btn):not(.dims-all-filter-btn)').forEach(function(tab) {
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
     * One ghost card per block type (plan/competency), each with its own count.
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

        list.querySelectorAll('.dims-card-item:not(.dims-ghost-card)').forEach((item) => {
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

        if (!state.rawDataset.hasactiveplans) {
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
            empty.textContent = labels.nocompetencies;
            empty.style.display = '';
        }
    }

    function showLoading(container, isLoading) {
        const loading = container.querySelector('.dims-loading-state');
        if (loading) {
            loading.style.display = isLoading ? '' : 'none';
        }
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
     * Surgically update the favourite-pill counts ("My Favourites (N)" and
     * "Show all (N)") without rebuilding the filter bar. Used by toggleFavourite
     * to preserve focus on the star button (WCAG 2.4.3).
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

    let resultsAnnounceTimer = null;

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
        clearTimeout(resultsAnnounceTimer);
        resultsAnnounceTimer = setTimeout(function() {
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
        }, 600);
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
     * Capture an identifier for the currently focused element inside the block
     * so focus can be restored after a DOM rebuild (WCAG 2.4.3).
     * @return {Object|null}
     */
    function captureFocusKey(container) {
        const active = document.activeElement;
        if (!active || !container.contains(active)) {
            return null;
        }
        if (active.matches('.dims-fav-filter-btn[data-fav-filter-type]')) {
            return {selector: '.dims-fav-filter-btn[data-fav-filter-type="' + active.dataset.favFilterType + '"]'};
        }
        if (active.matches('.dims-all-filter-btn[data-all-filter-type]')) {
            return {selector: '.dims-all-filter-btn[data-all-filter-type="' + active.dataset.allFilterType + '"]'};
        }
        if (active.matches('.dims-filter-tab[data-filter-field]')) {
            const field = active.dataset.filterField;
            const value = active.dataset.filterValue || '';
            return {selector: '.dims-filter-tab[data-filter-field="' + field + '"][data-filter-value="' + value + '"]'};
        }
        if (active.matches('.dims-clear-filters-btn[data-clear-type]')) {
            return {selector: '.dims-clear-filters-btn[data-clear-type="' + active.dataset.clearType + '"]'};
        }
        if (active.matches('.dims-filter-select[data-filter-field]')) {
            return {selector: '.dims-filter-select[data-filter-field="' + active.dataset.filterField + '"]'};
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

            // Update the card data in raw dataset.
            const cards = itemtype === 'plan' ? state.rawDataset.plancards : state.rawDataset.competencycards;
            for (let i = 0; i < cards.length; i++) {
                if (cards[i].id === itemid) {
                    cards[i].isfavourite = nowFav;
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
     * Load the dataset for a specific group or all groups (Phase 2).
     *
     * @param {HTMLElement} container Block container.
     * @param {Object} state Application state.
     * @param {Object} options Init options.
     * @param {string} group 'plan', 'competency', or '' for both.
     * @return {Promise}
     */
    function loadGroupDataset(container, state, options, group) {
        // Skip if already loaded.
        if (group === '') {
            if (state.fullDatasetLoaded.plan && state.fullDatasetLoaded.competency) {
                return Promise.resolve();
            }
        } else if (state.fullDatasetLoaded[group]) {
            return Promise.resolve();
        }

        showLoading(container, true);

        return fetchDataset(options.endpointmethod, {favouritesonly: false, loadgroup: group})
            .then((dataset) => {
                state.rawDataset.hasactiveplans = !!dataset.hasactiveplans;

                if (dataset.filtersettings) {
                    state.filterSettings = dataset.filtersettings;
                }

                if (typeof dataset.favouritesenabled !== 'undefined') {
                    state.favouritesEnabled = !!dataset.favouritesenabled;
                }

                // Merge: only update the group(s) that were loaded.
                if (group !== 'competency') {
                    state.rawDataset.plancards = dataset.plancards || [];
                    state.rawDataset.hasplancards = !!dataset.hasplancards;
                    state.totalplans = dataset.totalplans || state.totalplans;
                    state.hasnonfavouriteplans = false;
                    state.fullDatasetLoaded.plan = true;
                    state.cardsRendered.plan = false;
                }
                if (group !== 'plan') {
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
                showLoading(container, false);
                return null;
            })
            .catch(() => {
                showLoading(container, false);
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

                    // If full dataset not yet loaded for all groups, fetch remaining.
                    const bothLoaded = state.fullDatasetLoaded.plan && state.fullDatasetLoaded.competency;
                    const searchNeedsLoad = state.normalizedSearch && !bothLoaded
                        && (state.hasnonfavouriteplans || state.hasnonfavouritecompetencies);
                    if (searchNeedsLoad) {
                        return loadGroupDataset(container, state, options, '').then(() => {
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

        // Click delegator handles trail links, filter tabs and clear buttons;
        // branch count over the lint cap is acknowledged (refactor pending).
        // eslint-disable-next-line complexity
        container.addEventListener('click', (e) => {
            // Handle clickable trail item — call WS to set return context, then navigate.
            // Trail items are real <a> elements (WCAG 2.1.1 — Enter/Space work
            // natively via the browser). We only intercept the navigation when
            // we need to fire the set_return_context web service first.
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
                    ? state.hasnonfavouriteplans : state.hasnonfavouritecompetencies;
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

            // Handle "All items" pill click.
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
            if (!tab || tab.classList.contains('dims-fav-filter-btn') || tab.classList.contains('dims-all-filter-btn')) {
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

    function loadData(container, state, options) {
        clearError(container);
        showLoading(container, true);

        // Phase 1: if favourites are enabled, load only favourites first.
        const useFavouritesFirst = state.favouritesEnabled;
        const fetchArgs = useFavouritesFirst ? {favouritesonly: true} : {};

        return fetchDataset(options.endpointmethod, fetchArgs)
            // Phase-1/phase-2 favourites branching; branch count over the lint
            // cap is acknowledged (refactor pending).
            // eslint-disable-next-line complexity
            .then((dataset) => {
                state.rawDataset = {
                    hasactiveplans: !!dataset.hasactiveplans,
                    hasplancards: !!dataset.hasplancards,
                    hascompetencies: !!dataset.hascompetencies,
                    plancards: dataset.plancards || [],
                    competencycards: dataset.competencycards || []
                };

                if (dataset.filtersettings) {
                    state.filterSettings = dataset.filtersettings;
                }

                // Update favourites enabled from server response.
                if (typeof dataset.favouritesenabled !== 'undefined') {
                    state.favouritesEnabled = !!dataset.favouritesenabled;
                }

                // Store totals and per-group non-favourite flags.
                state.totalplans = dataset.totalplans || 0;
                state.totalcompetencies = dataset.totalcompetencies || 0;
                state.hasnonfavouriteplans = !!dataset.hasnonfavouriteplans;
                state.hasnonfavouritecompetencies = !!dataset.hasnonfavouritecompetencies;
                updateFavouriteCounts(state);

                const hasAnyNonFavourites = state.hasnonfavouriteplans || state.hasnonfavouritecompetencies;
                const favCards = state.rawDataset.plancards.length + state.rawDataset.competencycards.length;

                if (useFavouritesFirst && hasAnyNonFavourites && favCards > 0) {
                    // Check per-group: which groups have items but zero favourites.
                    const planHasItemsNoFavs = state.totalplans > 0 && state.favouriteCountPlan === 0;
                    const compHasItemsNoFavs = state.totalcompetencies > 0 && state.favouriteCountCompetency === 0;

                    // Pre-set fav filter for groups that HAVE favourites.
                    state.favouriteFilterActive.plan = state.favouriteCountPlan > 0 && state.hasnonfavouriteplans;
                    state.favouriteFilterActive.competency = state.favouriteCountCompetency > 0 && state.hasnonfavouritecompetencies;

                    if (planHasItemsNoFavs && compHasItemsNoFavs) {
                        // Both groups are empty — load everything.
                        return loadGroupDataset(container, state, options, '');
                    } else if (planHasItemsNoFavs) {
                        // Only plans need full load — keep competency favs in phase-1.
                        state.fullDatasetLoaded.competency = false;
                        return loadGroupDataset(container, state, options, 'plan');
                    } else if (compHasItemsNoFavs) {
                        // Only competencies need full load — keep plan favs in phase-1.
                        state.fullDatasetLoaded.plan = false;
                        return loadGroupDataset(container, state, options, 'competency');
                    }

                    // Both groups have some favourites — stay in phase-1 mode.
                    state.fullDatasetLoaded.plan = false;
                    state.fullDatasetLoaded.competency = false;
                } else if (useFavouritesFirst && hasAnyNonFavourites && favCards === 0) {
                    // No favourites exist — load full dataset immediately.
                    return loadGroupDataset(container, state, options, '');
                } else {
                    // Either favourites are disabled, no favourites exist, or
                    // all items are favourites — we already have everything.
                    state.fullDatasetLoaded.plan = true;
                    state.fullDatasetLoaded.competency = true;
                }

                resetRenderedState(container, state);
                rerender(container, state, options);
                showLoading(container, false);
                return null;
            })
            .catch(() => {
                showLoading(container, false);
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

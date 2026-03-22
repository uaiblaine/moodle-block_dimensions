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

define(['core/ajax', 'core/templates'], function(Ajax, Templates) {
    const BATCH_SIZE = 24;

    const normalizeText = (str) => (str || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    function createState(options) {
        return {
            rawDataset: {
                hasactiveplans: false,
                hasplancards: false,
                hascompetencies: false,
                plancards: [],
                competencycards: []
            },
            filteredDataset: {
                plancards: [],
                competencycards: []
            },
            searchTerm: '',
            normalizedSearch: '',
            activeFilters: {
                plan_tag1: '',
                plan_tag2: '',
                competency_tag1: '',
                competency_tag2: ''
            },
            favouriteFilterActive: {
                plan: false,
                competency: false
            },
            favouritesEnabled: !!options.favouritesenabled,
            filterSettings: options.filtersettings || {},
            renderToken: 0,
            filtersRendered: false,
            cardsRendered: {
                plan: false,
                competency: false
            },
            // Two-phase loading state.
            fullDatasetLoaded: false,
            hasnonfavourites: false,
            totalplans: 0,
            totalcompetencies: 0,
            favouriteCountPlan: 0,
            favouriteCountCompetency: 0
        };
    }

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

        html.push('<div class="dims-filters-bar" role="toolbar" aria-label="' + labels.filterall + '">');

        // Favourite / All pills (always first, if enabled).
        // Counts are per-type: plan pills show plan counts, competency pills show competency counts.
        if (state.favouritesEnabled) {
            const typeFavCount = type === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
            const typeTotal = type === 'plan' ? state.totalplans : state.totalcompetencies;
            const isFavActive = state.favouriteFilterActive[type];

            if (typeFavCount > 0) {
                hasAnyFilter = true;
                html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="fav_' + type + '">');
                html.push('<div class="dims-filter-tabs" role="tablist">');

                // "My Favourites (N)" pill — count is per-type.
                html.push('<button type="button" class="dims-filter-tab dims-fav-filter-btn'
                    + (isFavActive ? ' active' : '') + '" role="tab" aria-selected="'
                    + (isFavActive ? 'true' : 'false') + '" data-fav-filter-type="' + type + '">'
                    + '<i class="fa fa-star dims-fav-filter-icon" aria-hidden="true"></i> '
                    + escapeHtml(labels.myfavourites)
                    + ' <span class="dims-filter-count">(' + typeFavCount + ')</span>'
                    + '</button>');

                // "Show all (N)" pill — count is per-type total.
                html.push('<button type="button" class="dims-filter-tab dims-all-filter-btn'
                    + (!isFavActive ? ' active' : '') + '" role="tab" aria-selected="'
                    + (!isFavActive ? 'true' : 'false') + '" data-all-filter-type="' + type + '">'
                    + escapeHtml(labelWithCount(labels.showallitems, typeTotal))
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

            if (mode === 'tabs') {
                html.push('<div class="dims-filter-tabs-wrapper" data-filter-group="' + field + '">');
                html.push('<div class="dims-filter-tabs" role="tablist">');
                html.push('<button type="button" class="dims-filter-tab ' + (selectedValue === '' ? 'active' : '') + '" role="tab" aria-selected="' + (selectedValue === '' ? 'true' : 'false') + '" data-filter-field="' + field + '" data-filter-value="">' + labels.filterall + '</button>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<button type="button" class="dims-filter-tab ' + (isSelected ? 'active' : '') + '" role="tab" aria-selected="' + (isSelected ? 'true' : 'false') + '" data-filter-field="' + field + '" data-filter-value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</button>');
                });
                html.push('</div></div>');
            } else {
                html.push('<div class="dims-filter-dropdown-wrapper" data-filter-group="' + field + '">');
                html.push('<select class="dims-filter-select" data-filter-field="' + field + '" aria-label="' + tag + '">');
                html.push('<option value=""' + (selectedValue === '' ? ' selected' : '') + '>' + labels.filterall + '</option>');
                values.forEach((value) => {
                    const isSelected = selectedValue === value;
                    html.push('<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(value) + '</option>');
                });
                html.push('</select></div>');
            }
        });

        html.push('</div>');
        host.innerHTML = hasAnyFilter ? html.join('') : '';
    }

    function syncFilterActiveState(container, state) {
        container.querySelectorAll('.dims-filter-tab:not(.dims-fav-filter-btn):not(.dims-all-filter-btn)').forEach(function(tab) {
            var field = tab.dataset.filterField;
            var value = tab.dataset.filterValue || '';
            var isActive = (state.activeFilters[field] || '') === value;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        container.querySelectorAll('.dims-filter-select').forEach(function(select) {
            var field = select.dataset.filterField;
            select.value = state.activeFilters[field] || '';
        });

        // Sync favourite / all pills.
        container.querySelectorAll('.dims-fav-filter-btn').forEach(function(btn) {
            var favType = btn.dataset.favFilterType;
            var isActive = state.favouriteFilterActive[favType];
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        container.querySelectorAll('.dims-all-filter-btn').forEach(function(btn) {
            var allType = btn.dataset.allFilterType;
            var isActive = !state.favouriteFilterActive[allType];
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function isCardVisible(card, state, type) {
        // Favourite filter check.
        if (state.favouriteFilterActive[type] && !card.isfavourite) {
            return false;
        }

        const tag1filter = state.activeFilters[type + '_tag1'];
        const tag2filter = state.activeFilters[type + '_tag2'];

        if (tag1filter && card.tag1 !== tag1filter) {
            return false;
        }
        if (tag2filter && card.tag2 !== tag2filter) {
            return false;
        }
        if (state.normalizedSearch) {
            return normalizeText(card.name || '').includes(state.normalizedSearch);
        }

        return true;
    }

    function applyFilters(state) {
        state.filteredDataset.plancards = state.rawDataset.plancards.filter((card) => isCardVisible(card, state, 'plan'));
        state.filteredDataset.competencycards = state.rawDataset.competencycards.filter((card) => isCardVisible(card, state, 'competency'));
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
                li.className = 'col-12 col-sm-6 col-lg-4 mb-3 dims-card-item';
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
                            return;
                        }

                        const fragment = document.createDocumentFragment();
                        nodes.forEach((node) => fragment.appendChild(node));
                        list.appendChild(fragment);

                        globalThis.requestAnimationFrame(() => renderBatch(start + BATCH_SIZE));
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
        });
    }

    /**
     * Render or remove ghost cards that invite the user to load all items.
     * One ghost card per block type (plan/competency), each with its own count.
     */
    function renderGhostCards(container, state, labels) {
        // Remove any existing ghost cards.
        container.querySelectorAll('.dims-ghost-card').forEach(el => el.remove());

        // Only show ghost cards if we are in favourites-only mode and there are more items.
        if (!state.favouritesEnabled || state.fullDatasetLoaded || !state.hasnonfavourites) {
            return;
        }

        const favPlans = state.rawDataset.plancards.length;
        const favComps = state.rawDataset.competencycards.length;

        // Ghost card for plans block.
        const planRemaining = state.totalplans - favPlans;
        if (planRemaining > 0) {
            const planList = container.querySelector('[data-cards-type="plan"]');
            if (planList) {
                appendGhostCardTo(planList, planRemaining, labels, 'plan');
            }
        }

        // Ghost card for competencies block.
        const compRemaining = state.totalcompetencies - favComps;
        if (compRemaining > 0) {
            const compList = container.querySelector('[data-cards-type="competency"]');
            if (compList) {
                appendGhostCardTo(compList, compRemaining, labels, 'competency');
            }
        }
    }

    function appendGhostCardTo(list, remainingCount, labels, type) {
        const ghostLabel = labelWithCount(labels.showallitems, remainingCount);
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
        if (text) {
            text.textContent = message;
        }
        errorBox.style.display = '';
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

            // Update aria-label and title.
            const label = nowFav ? options.labels.removefromfavourites : options.labels.addtofavourites;
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);

            // Update the card data in raw dataset.
            const cards = itemtype === 'plan' ? state.rawDataset.plancards : state.rawDataset.competencycards;
            for (let i = 0; i < cards.length; i++) {
                if (cards[i].id === itemid) {
                    cards[i].isfavourite = nowFav;
                    break;
                }
            }

            // Update favourite counts.
            updateFavouriteCounts(state);

            // Re-render filters to update pill counts.
            state.filtersRendered = false;

            // If favourite filter is active and count dropped to 0, deactivate and load all.
            const typeFavCount = itemtype === 'plan' ? state.favouriteCountPlan : state.favouriteCountCompetency;
            if (state.favouriteFilterActive[itemtype] && typeFavCount === 0) {
                state.favouriteFilterActive[itemtype] = false;
                if (!state.fullDatasetLoaded && state.hasnonfavourites) {
                    loadFullDataset(container, state, options);
                    return;
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
        }).catch(function() {
            delete btn.dataset.favPending;
        });
    }

    function updateFavouriteCounts(state) {
        state.favouriteCountPlan = state.rawDataset.plancards.filter(c => c.isfavourite).length;
        state.favouriteCountCompetency = state.rawDataset.competencycards.filter(c => c.isfavourite).length;
    }

    function rerender(container, state, options) {
        applyFilters(state);

        if (!state.filtersRendered) {
            renderFilterControls(container, 'plan', state.rawDataset.plancards, state, options.labels);
            renderFilterControls(container, 'competency', state.rawDataset.competencycards, state, options.labels);
            state.filtersRendered = true;
        }

        syncFilterActiveState(container, state);

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
        }).catch(() => {
            showError(container, options.labels.loaderror);
        });
    }

    /**
     * Load the full dataset (Phase 2). Called when user wants all items.
     */
    function loadFullDataset(container, state, options) {
        if (state.fullDatasetLoaded) {
            return Promise.resolve();
        }

        showLoading(container, true);

        return fetchDataset(options.endpointmethod, {favouritesonly: false})
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

                if (typeof dataset.favouritesenabled !== 'undefined') {
                    state.favouritesEnabled = !!dataset.favouritesenabled;
                }

                state.totalplans = dataset.totalplans || 0;
                state.totalcompetencies = dataset.totalcompetencies || 0;
                state.hasnonfavourites = false;
                state.fullDatasetLoaded = true;
                updateFavouriteCounts(state);

                resetRenderedState(container, state);
                rerender(container, state, options);
                showLoading(container, false);
            })
            .catch(() => {
                showLoading(container, false);
                showError(container, options.labels.loaderror);
            });
    }

    /**
     * Trigger full dataset load and then switch to showing all items.
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

        if (state.fullDatasetLoaded) {
            rerender(container, state, options);
            return;
        }

        loadFullDataset(container, state, options);
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
                        state.filtersRendered = false;
                    }

                    // If full dataset not yet loaded, fetch it first.
                    if (state.normalizedSearch && !state.fullDatasetLoaded && state.hasnonfavourites) {
                        loadFullDataset(container, state, options).then(() => {
                            state.searchTerm = searchInput.value.trim();
                            state.normalizedSearch = normalizeText(state.searchTerm);
                            rerender(container, state, options);
                        });
                    } else {
                        rerender(container, state, options);
                    }
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

        container.addEventListener('click', (e) => {
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

                if (!state.favouriteFilterActive[favType] && !state.fullDatasetLoaded && state.hasnonfavourites) {
                    // Deactivating favourite filter: need full dataset.
                    showAllItems(container, state, options, favType);
                    return;
                }

                syncFilterActiveState(container, state);
                applyFilters(state);
                applyVisibility(container, favType,
                    favType === 'plan' ? state.filteredDataset.plancards : state.filteredDataset.competencycards);
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

                // Store totals for pill counts.
                state.totalplans = dataset.totalplans || 0;
                state.totalcompetencies = dataset.totalcompetencies || 0;
                state.hasnonfavourites = !!dataset.hasnonfavourites;
                updateFavouriteCounts(state);

                const favCards = state.rawDataset.plancards.length + state.rawDataset.competencycards.length;

                if (useFavouritesFirst && state.hasnonfavourites && favCards > 0) {
                    // Phase 1: we got only favourites, there are more items.
                    state.fullDatasetLoaded = false;
                    // Activate favourite filter only for types that have favourites.
                    state.favouriteFilterActive.plan = state.favouriteCountPlan > 0;
                    state.favouriteFilterActive.competency = state.favouriteCountCompetency > 0;
                } else if (useFavouritesFirst && state.hasnonfavourites && favCards === 0) {
                    // No favourites exist — load full dataset immediately.
                    return loadFullDataset(container, state, options);
                } else {
                    // Either favourites are disabled, no favourites exist, or
                    // all items are favourites — we already have everything.
                    state.fullDatasetLoaded = true;
                }

                resetRenderedState(container, state);
                rerender(container, state, options);
                showLoading(container, false);
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

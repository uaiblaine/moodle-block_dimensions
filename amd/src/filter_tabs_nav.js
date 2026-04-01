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
 * Horizontal-scrolling pill tab navigation for block_dimensions filters.
 *
 * Adapted from the Apple MacBook Neo tabnav-pill pattern. Provides:
 * - Smooth horizontal scroll when tabs overflow the container.
 * - Animated indicator that follows the active tab.
 * - Left/right paddle (arrow) buttons with auto-hide at edges.
 * - ResizeObserver for responsive re-centering.
 * - Keyboard navigation (ArrowLeft / ArrowRight).
 * - Reduced-motion support.
 *
 * @module     block_dimensions/filter_tabs_nav
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-disable jsdoc/require-jsdoc */

define([], function() {
    'use strict';

    var EASE_OUT_CUBIC = function(t) {
        return 1 - Math.pow(1 - t, 3);
    };

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function parseDurationMs(rawValue, fallbackMs) {
        fallbackMs = fallbackMs || 320;
        var value = String(rawValue || '').trim();
        if (!value) {
            return fallbackMs;
        }
        if (value.indexOf('ms') === value.length - 2) {
            return parseFloat(value);
        }
        if (value.charAt(value.length - 1) === 's') {
            return parseFloat(value) * 1000;
        }
        var numeric = parseFloat(value);
        return isFinite(numeric) ? numeric : fallbackMs;
    }

    // SVG chevron icons for paddles.
    var PADDLE_LEFT_SVG = '<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M7 1L1 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var PADDLE_RIGHT_SVG = '<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    /**
     * Wrap the inner content of a .dims-filter-tabs element with mask/indicator/paddles.
     *
     * Transforms:
     *   <div class="dims-filter-tabs" role="tablist">
     *     <button class="dims-filter-tab">…</button>
     *     ...
     *   </div>
     *
     * Into:
     *   <div class="dims-filter-tabs" role="tablist">
     *     <div class="dims-filter-tabs-mask">
     *       <div class="dims-filter-tabs-items">
     *         <button class="dims-filter-tab">…</button>
     *         ...
     *       </div>
     *     </div>
     *     <div class="dims-filter-tabs-indicator"></div>
     *     <div class="dims-filter-tabs-paddles">
     *       <button class="dims-filter-tabs-paddle dims-filter-tabs-paddle-left" ...>…</button>
     *       <button class="dims-filter-tabs-paddle dims-filter-tabs-paddle-right" ...>…</button>
     *     </div>
     *   </div>
     *
     * @param {HTMLElement} tabsEl The .dims-filter-tabs element.
     */
    function wrapTabsContent(tabsEl) {
        // Collect all tab buttons.
        var tabs = [];
        var child = tabsEl.firstChild;
        while (child) {
            var next = child.nextSibling;
            tabs.push(child);
            child = next;
        }

        // Create items container.
        var itemsEl = document.createElement('div');
        itemsEl.className = 'dims-filter-tabs-items';
        tabs.forEach(function(tab) {
            itemsEl.appendChild(tab);
        });

        // Create mask.
        var maskEl = document.createElement('div');
        maskEl.className = 'dims-filter-tabs-mask';
        maskEl.appendChild(itemsEl);

        // Create indicator.
        var indicatorEl = document.createElement('div');
        indicatorEl.className = 'dims-filter-tabs-indicator';

        // Create paddles.
        var paddlesEl = document.createElement('div');
        paddlesEl.className = 'dims-filter-tabs-paddles';

        var paddleLeft = document.createElement('button');
        paddleLeft.type = 'button';
        paddleLeft.className = 'dims-filter-tabs-paddle dims-filter-tabs-paddle-left dims-filter-tabs-paddle-hidden';
        paddleLeft.setAttribute('aria-hidden', 'true');
        paddleLeft.setAttribute('aria-label', 'Scroll filters left');
        paddleLeft.tabIndex = -1;
        paddleLeft.innerHTML = PADDLE_LEFT_SVG;

        var paddleRight = document.createElement('button');
        paddleRight.type = 'button';
        paddleRight.className = 'dims-filter-tabs-paddle dims-filter-tabs-paddle-right dims-filter-tabs-paddle-hidden';
        paddleRight.setAttribute('aria-hidden', 'true');
        paddleRight.setAttribute('aria-label', 'Scroll filters right');
        paddleRight.tabIndex = -1;
        paddleRight.innerHTML = PADDLE_RIGHT_SVG;

        paddlesEl.appendChild(paddleLeft);
        paddlesEl.appendChild(paddleRight);

        // Assemble.
        tabsEl.innerHTML = '';
        tabsEl.appendChild(maskEl);
        tabsEl.appendChild(indicatorEl);
        tabsEl.appendChild(paddlesEl);
    }

    /**
     * Controller for one .dims-filter-tabs-wrapper element.
     *
     * @param {HTMLElement} wrapperEl The .dims-filter-tabs-wrapper element.
     * @constructor
     */
    function FilterTabsNav(wrapperEl) {
        this.wrapperEl = wrapperEl;
        this.platterEl = wrapperEl.querySelector('.dims-filter-tabs');
        if (!this.platterEl) {
            return;
        }

        // Wrap the tabs content with mask/indicator/paddles.
        wrapTabsContent(this.platterEl);

        this.maskEl = this.platterEl.querySelector('.dims-filter-tabs-mask');
        this.itemsEl = this.platterEl.querySelector('.dims-filter-tabs-items');
        this.indicatorEl = this.platterEl.querySelector('.dims-filter-tabs-indicator');
        this.paddleLeftEl = this.platterEl.querySelector('.dims-filter-tabs-paddle-left');
        this.paddleRightEl = this.platterEl.querySelector('.dims-filter-tabs-paddle-right');

        this.scrollAnimationFrame = 0;
        this.resizeDebounceTimer = 0;
        this.reducedMotion = false;
        this._destroyed = false;

        // Bind methods.
        this._onPaddleLeftClick = this._onPaddleLeftClick.bind(this);
        this._onPaddleRightClick = this._onPaddleRightClick.bind(this);
        this._onResize = this._onResize.bind(this);
        this._onReducedMotionChange = this._onReducedMotionChange.bind(this);
        this._onKeyDown = this._onKeyDown.bind(this);

        // Reduced motion.
        this._reducedMotionMql = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.reducedMotion = this._reducedMotionMql.matches;
        if (this._reducedMotionMql.addEventListener) {
            this._reducedMotionMql.addEventListener('change', this._onReducedMotionChange);
        }

        // ResizeObserver.
        this._resizeObserver = new ResizeObserver(this._onResize);
        this._resizeObserver.observe(this.wrapperEl);

        // Paddle clicks.
        this.paddleLeftEl.addEventListener('click', this._onPaddleLeftClick);
        this.paddleRightEl.addEventListener('click', this._onPaddleRightClick);

        // Keyboard navigation within tablist.
        this.platterEl.addEventListener('keydown', this._onKeyDown);

        // Initial setup — disable transitions, position, then enable.
        this.platterEl.classList.add('dims-filter-tabs-no-transition');
        var self = this;
        requestAnimationFrame(function() {
            if (self._destroyed) {
                return;
            }
            self.updateActiveTab();
            requestAnimationFrame(function() {
                if (!self._destroyed) {
                    self.platterEl.classList.remove('dims-filter-tabs-no-transition');
                }
            });
        });

        // Store reference on the wrapper for cleanup.
        wrapperEl._dimsFilterTabsNav = this;
    }

    /**
     * Get the currently active tab button.
     * @return {HTMLElement|null}
     */
    FilterTabsNav.prototype._getActiveTab = function() {
        return this.itemsEl.querySelector('.dims-filter-tab.active');
    };

    /**
     * Get all tab buttons.
     * @return {HTMLElement[]}
     */
    FilterTabsNav.prototype._getAllTabs = function() {
        return Array.prototype.slice.call(this.itemsEl.querySelectorAll('.dims-filter-tab'));
    };

    /**
     * Read a CSS custom property as a pixel value.
     * @param {string} name
     * @param {number} fallback
     * @return {number}
     */
    FilterTabsNav.prototype._parseCssPx = function(name, fallback) {
        var raw = getComputedStyle(this.platterEl).getPropertyValue(name);
        var parsed = parseFloat(raw);
        return isFinite(parsed) ? parsed : (fallback || 0);
    };

    /**
     * Get the scroll animation duration in ms.
     * @return {number}
     */
    FilterTabsNav.prototype._getScrollDurationMs = function() {
        var raw = getComputedStyle(this.platterEl).getPropertyValue('--dims-tabs-scroll-duration');
        return parseDurationMs(raw, 320);
    };

    /**
     * Compute animation / layout properties for the current state.
     * @param {HTMLElement} [targetEl] The element to center (defaults to active tab).
     * @return {Object}
     */
    FilterTabsNav.prototype._computeProps = function(targetEl) {
        var activeTab = this._getActiveTab();
        var referenceEl = targetEl || activeTab;

        if (!activeTab || !referenceEl) {
            return {
                scrollable: false,
                indicatorStart: '4px',
                indicatorWidth: '0px',
                scrollLeft: 0,
                disableLeftPaddle: true,
                disableRightPaddle: true,
                contentPosition: 'leftEdge'
            };
        }

        var activeLeft = activeTab.offsetLeft;
        var activeWidth = activeTab.offsetWidth;
        var itemLeft = referenceEl.offsetLeft;
        var itemWidth = referenceEl.offsetWidth;
        var itemCenter = itemWidth / 2;

        var platterWidth = this.platterEl.offsetWidth;
        var platterPadding = this._parseCssPx('--dims-tabs-platter-padding', 4);
        var platterCenter = platterWidth / 2;
        var itemsWidth = this.itemsEl.scrollWidth;

        var contentPosition = 'inBetweenEdges';
        if (itemLeft + itemCenter > itemsWidth - platterCenter) {
            contentPosition = 'rightEdge';
        } else if (itemLeft + itemCenter < platterCenter) {
            contentPosition = 'leftEdge';
        }

        var indicatorStart;
        var scrollLeft;

        if (contentPosition === 'rightEdge') {
            indicatorStart = activeLeft + (platterWidth - itemsWidth) - platterPadding;
            scrollLeft = itemsWidth - platterWidth + platterPadding * 2;
        } else if (contentPosition === 'leftEdge') {
            indicatorStart = activeLeft + platterPadding;
            scrollLeft = 0;
        } else {
            indicatorStart = platterCenter - itemLeft - itemCenter + activeLeft;
            scrollLeft = itemLeft - (platterCenter - itemCenter) + platterPadding;
        }

        var scrollable = itemsWidth + platterPadding * 2 - 1 > platterWidth;

        return {
            contentPosition: contentPosition,
            indicatorStart: indicatorStart + 'px',
            indicatorWidth: activeWidth + 'px',
            scrollLeft: scrollLeft,
            scrollable: scrollable,
            disableLeftPaddle: contentPosition === 'leftEdge',
            disableRightPaddle: contentPosition === 'rightEdge'
        };
    };

    /**
     * Animate scroll of the mask element.
     * @param {number} targetLeft
     */
    FilterTabsNav.prototype._animateMaskScroll = function(targetLeft) {
        cancelAnimationFrame(this.scrollAnimationFrame);

        if (this.reducedMotion) {
            this.maskEl.scrollLeft = targetLeft;
            return;
        }

        var startLeft = this.maskEl.scrollLeft;
        var delta = targetLeft - startLeft;
        var durationMs = this._getScrollDurationMs();
        var startTime = performance.now();
        var maskEl = this.maskEl;
        var self = this;

        var tick = function(now) {
            var progress = clamp((now - startTime) / durationMs, 0, 1);
            var eased = EASE_OUT_CUBIC(progress);
            maskEl.scrollLeft = Math.round(startLeft + delta * eased);
            if (progress < 1) {
                self.scrollAnimationFrame = requestAnimationFrame(tick);
            }
        };

        this.scrollAnimationFrame = requestAnimationFrame(tick);
    };

    /**
     * Update paddle visibility based on computed props.
     * @param {Object} props
     */
    FilterTabsNav.prototype._updatePaddles = function(props) {
        if (!props.scrollable) {
            this.paddleLeftEl.classList.add('dims-filter-tabs-paddle-hidden');
            this.paddleLeftEl.disabled = true;
            this.paddleRightEl.classList.add('dims-filter-tabs-paddle-hidden');
            this.paddleRightEl.disabled = true;
            this.maskEl.classList.add('dims-filter-tabs-mask-noscroll');
            return;
        }

        this.maskEl.classList.remove('dims-filter-tabs-mask-noscroll');

        this.paddleLeftEl.classList.toggle('dims-filter-tabs-paddle-hidden', props.disableLeftPaddle);
        this.paddleLeftEl.disabled = props.disableLeftPaddle;

        this.paddleRightEl.classList.toggle('dims-filter-tabs-paddle-hidden', props.disableRightPaddle);
        this.paddleRightEl.disabled = props.disableRightPaddle;
    };

    /**
     * Re-center the view on the active tab and update the indicator.
     * @param {HTMLElement} [targetEl] Optional element to center on.
     */
    FilterTabsNav.prototype._centerItem = function(targetEl) {
        var props = this._computeProps(targetEl);

        this.platterEl.style.setProperty('--dims-tabs-indicator-start', props.indicatorStart);
        this.platterEl.style.setProperty('--dims-tabs-indicator-width', props.indicatorWidth);

        if (props.scrollable) {
            this._animateMaskScroll(props.scrollLeft);
        } else {
            this.maskEl.scrollLeft = 0;
        }

        this._updatePaddles(props);
    };

    /**
     * Public: re-compute the indicator position and scroll after the active tab changes.
     */
    FilterTabsNav.prototype.updateActiveTab = function() {
        this._centerItem();
    };

    /**
     * Scroll one "page" in the given direction by finding the next partially hidden tab.
     * @param {number} direction -1 for left, +1 for right.
     */
    FilterTabsNav.prototype._scrollByDirection = function(direction) {
        var tabs = this._getAllTabs();
        var maskRect = this.maskEl.getBoundingClientRect();
        var i;

        if (direction > 0) {
            // Find the first tab whose right edge is beyond the mask's right edge.
            for (i = 0; i < tabs.length; i++) {
                var tabRect = tabs[i].getBoundingClientRect();
                if (tabRect.right > maskRect.right + 2) {
                    this._centerItem(tabs[i]);
                    return;
                }
            }
        } else {
            // Find the last tab whose left edge is before the mask's left edge.
            for (i = tabs.length - 1; i >= 0; i--) {
                var tabRectL = tabs[i].getBoundingClientRect();
                if (tabRectL.left < maskRect.left - 2) {
                    this._centerItem(tabs[i]);
                    return;
                }
            }
        }
    };

    FilterTabsNav.prototype._onPaddleLeftClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        this._scrollByDirection(-1);
    };

    FilterTabsNav.prototype._onPaddleRightClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        this._scrollByDirection(1);
    };

    FilterTabsNav.prototype._onResize = function() {
        if (this._destroyed) {
            return;
        }
        this.platterEl.classList.add('dims-filter-tabs-no-transition');
        this._centerItem();
        var self = this;
        clearTimeout(this.resizeDebounceTimer);
        this.resizeDebounceTimer = window.setTimeout(function() {
            if (!self._destroyed) {
                self.platterEl.classList.remove('dims-filter-tabs-no-transition');
            }
        }, 250);
    };

    FilterTabsNav.prototype._onReducedMotionChange = function(e) {
        this.reducedMotion = !!e.matches;
    };

    FilterTabsNav.prototype._onKeyDown = function(e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
            return;
        }

        var tabs = this._getAllTabs();
        var focusedIndex = tabs.indexOf(document.activeElement);
        if (focusedIndex === -1) {
            return;
        }

        e.preventDefault();
        var nextIndex;
        if (e.key === 'ArrowRight') {
            nextIndex = (focusedIndex + 1) % tabs.length;
        } else {
            nextIndex = (focusedIndex - 1 + tabs.length) % tabs.length;
        }

        tabs[nextIndex].focus({preventScroll: true});
        tabs[nextIndex].click();
    };

    /**
     * Clean up all listeners and observers.
     */
    FilterTabsNav.prototype.destroy = function() {
        this._destroyed = true;
        cancelAnimationFrame(this.scrollAnimationFrame);
        clearTimeout(this.resizeDebounceTimer);

        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
        }

        if (this._reducedMotionMql && this._reducedMotionMql.removeEventListener) {
            this._reducedMotionMql.removeEventListener('change', this._onReducedMotionChange);
        }

        if (this.paddleLeftEl) {
            this.paddleLeftEl.removeEventListener('click', this._onPaddleLeftClick);
        }
        if (this.paddleRightEl) {
            this.paddleRightEl.removeEventListener('click', this._onPaddleRightClick);
        }
        if (this.platterEl) {
            this.platterEl.removeEventListener('keydown', this._onKeyDown);
        }

        if (this.wrapperEl) {
            delete this.wrapperEl._dimsFilterTabsNav;
        }
    };

    /**
     * Initialize FilterTabsNav on all .dims-filter-tabs-wrapper elements within a container.
     *
     * @param {HTMLElement} container
     * @return {FilterTabsNav[]} Array of instances created.
     */
    function initAll(container) {
        var wrappers = container.querySelectorAll('.dims-filter-tabs-wrapper');
        var instances = [];
        for (var i = 0; i < wrappers.length; i++) {
            // Skip wrappers that already have an instance.
            if (wrappers[i]._dimsFilterTabsNav) {
                instances.push(wrappers[i]._dimsFilterTabsNav);
                continue;
            }
            // Skip wrappers without a tabs element.
            if (!wrappers[i].querySelector('.dims-filter-tabs')) {
                continue;
            }
            instances.push(new FilterTabsNav(wrappers[i]));
        }
        return instances;
    }

    /**
     * Destroy all FilterTabsNav instances within a container.
     *
     * @param {HTMLElement} container
     */
    function destroyAll(container) {
        var wrappers = container.querySelectorAll('.dims-filter-tabs-wrapper');
        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i]._dimsFilterTabsNav) {
                wrappers[i]._dimsFilterTabsNav.destroy();
            }
        }
    }

    /**
     * Update the active tab indicator on all instances within a container.
     *
     * @param {HTMLElement} container
     */
    function updateAll(container) {
        var wrappers = container.querySelectorAll('.dims-filter-tabs-wrapper');
        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i]._dimsFilterTabsNav) {
                wrappers[i]._dimsFilterTabsNav.updateActiveTab();
            }
        }
    }

    return {
        FilterTabsNav: FilterTabsNav,
        initAll: initAll,
        destroyAll: destroyAll,
        updateAll: updateAll
    };
});

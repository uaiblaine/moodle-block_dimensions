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
 * The filter line of one section of the block: its folding, the search that grows over it and its
 * suggestions, the Filters panel and its sheet, the row of chips under it.
 *
 * The rules are the fleet's filter line contract (moodle-dev/docs/filter-line/CONTRACT.md,
 * sections 1 to 4 and 8), written for this plugin after theme_dimensions' categoryline and
 * local_dimensions' filter_line: the same behaviour, no import across plugins. The block has two
 * lines, one per section, and a page can hold more than one block, so every line is an instance of
 * its own ({@see create}); nothing here is shared between two lines but the module's code.
 *
 * The server draws the frame of the line (templates/filter_line.mustache) and filters.js fills what
 * depends on the dataset - the status pills of the scope, the star, the tag groups of the panel -
 * and filters the cards; this module adds presentation and keyboard, and every filter it applies
 * it applies through a hook filters.js gives it.
 *
 * Folding ({@see fit}): the line measures its OWN width, never the viewport's, and takes the first
 * step at which its controls fit on one line - full; Filters without its word; the search as an
 * icon; two lines, the scope on the second. It measures with every cell of the scope drawn and with
 * no transition running, so the step follows the width and the language and never the counts of
 * the moment. The steps are classes on the line, which the stylesheet reads.
 *
 * The search grows over the line from the third step ({@see openSearch}): the other controls slide
 * out, the field takes the line and a close button its last place; Escape or that button closes it
 * and gives the focus back to the control that opened it, without opening it again. A text left in
 * force while the field is folded away shows as a chip in the row under the line.
 *
 * The suggestions ({@see render}) are one listbox under the line, the field its combobox with the
 * active option named by aria-activedescendant, so the focus never leaves the field. Filters come
 * first - the scope's buckets, the star and every value of the panel - whose label or group matches
 * the typed text, accent- and case-insensitively, word starts first and then by count, each with
 * the count filters.js gives for it WITHOUT the typed text; then the section's cards whose names
 * match. Choosing a filter empties the field and applies it, and the search stays open; choosing a
 * card hands it to filters.js.
 *
 * Every floating layer - the listbox and the Filters panel - is placed under the line's real
 * bottom, measured ({@see place}); on the two-line step the panel is a bottom sheet instead.
 *
 * Nothing here calls a web service or composes a word: every label, heading and count it shows is
 * read off the controls the server drew or asked of filters.js.
 *
 * @module     block_dimensions/filter_line
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/pending'], function(Pending) {

    /** @type {Object} Every hook this module reads, all of them written by templates/filter_line.mustache. */
    const SELECTORS = {
        line: '[data-region="dims-line"]',
        search: '[data-region="dims-line-search"]',
        field: 'input[type="search"]',
        eraser: '[data-action="dims-search-clear"]',
        opener: '[data-action="dims-search-open"]',
        closer: '[data-action="dims-search-close"]',
        listbox: '[data-region="dims-line-suggest"]',
        scope: '[data-region="dims-line-scope"]',
        cell: '[role="radio"]',
        star: '[data-region="dims-line-star"]',
        filters: '[data-region="dims-line-filters"]',
        toggle: '[data-action="dims-filters-toggle"]',
        panel: '[data-region="dims-line-panel"]',
        show: '[data-action="dims-panel-show"]',
        showcount: '[data-region="dims-panel-count"]',
        chips: '[data-region="dims-line-chips"]',
        chiplist: '[data-region="dims-line-chiplist"]',
        clearall: '[data-action="dims-clear-all"]',
        option: '[role="option"]',
    };

    /** @type {String[]} The folding steps in the order they are tried. */
    const STEPS = ['s1', 's2', 's3', 's4'];

    /** @type {Object} The class each step writes on the line, which the stylesheet reads. */
    const STEP_CLASSES = {
        s1: 'dims-line--s1',
        s2: 'dims-line--s2',
        s3: 'dims-line--s3',
        s4: 'dims-line--s4',
    };

    /**
     * @type {Array} What is tried, in order, as [step, whether the field is folded into its icon]: the
     * third step folds it, and the fourth keeps the field on its first line while it fits there.
     */
    const TRIES = [['s1', false], ['s2', false], ['s3', true], ['s4', false], ['s4', true]];

    /** @type {String[]} The steps at which the search grows over the line; at the first two nothing moves. */
    const MORPH_STEPS = ['s3', 's4'];

    /** @type {String} The step whose Filters panel is a bottom sheet and whose scope has a line of its own. */
    const TWO_LINES = 's4';

    /** @type {Object} The classes this module writes. */
    const CLASSES = {
        measuring: 'dims-line--measuring',
        searching: 'dims-line--searching',
        folded: 'dims-line--folded',
        sheet: 'dims-line-toolbar--sheet',
        fadeleft: 'dims-scroll--fade-left',
        faderight: 'dims-scroll--fade-right',
        active: 'dims-line-option--active',
        anchored: 'dims-line-suggest--anchored',
        mark: 'dims-line-mark',
    };

    /** @type {Number} Pixels between the line's real bottom and a layer placed under it: the stylesheet's own gap. */
    const GAP = 6;

    /** @type {Number} Pixels kept clear between a revealed item and the edge of the row it scrolls in. */
    const REVEAL_PAD = 16;

    /** @type {Number} The search's own floor: the block filters from the first character typed. */
    const MIN_TERM = 1;

    /** @type {Number} How many filters the empty field offers to start with: the highest counts. */
    const START_COUNT = 5;

    /** @type {Number} How many matching filters a typed text is offered. */
    const MATCH_COUNT = 8;

    /** @type {Number} How many matching cards a typed text is offered, after the filters. */
    const ITEM_COUNT = 5;

    /** @type {Number} The narrowest the listbox is drawn where it hangs under the field rather than across the line. */
    const MIN_LIST_WIDTH = 288;

    /** @type {Number} Milliseconds a Tab press counts as the reason the field was focused. */
    const TAB_INTENT = 1000;

    /**
     * Text with its accents taken off and in lower case, character for character, so a match found in it
     * is a match in the text it came from.
     *
     * @param {String} text Any text.
     * @return {String}
     */
    const fold = (text) => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    /**
     * The fleet's normalise rule for matching: accents off, lower case, trimmed.
     *
     * @param {String} text Any text.
     * @return {String}
     */
    const normalise = (text) => fold(text || '').trim();

    /**
     * How well a normalised text matches a normalised term: 0 where a word starts with it, 1 where it is
     * inside a word, -1 where it is not there at all.
     *
     * @param {String} text The normalised text.
     * @param {String} term The normalised term.
     * @return {Number}
     */
    const matchOf = (text, term) => {
        let at = text.indexOf(term);
        if (at < 0) {
            return -1;
        }
        while (at >= 0) {
            if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {
                return 0;
            }
            at = text.indexOf(term, at + 1);
        }
        return 1;
    };

    /**
     * The rank of a filter for a term: its label's word start first, then its group's, then inside its
     * label, then inside its group; -1 where neither holds the term.
     *
     * @param {Object} item A filter: label and group.
     * @param {String} term The normalised term.
     * @return {Number}
     */
    const rankOf = (item, term) => {
        const label = matchOf(normalise(item.label), term);
        const group = matchOf(normalise(item.group), term);
        if (label === 0) {
            return 0;
        }
        if (group === 0) {
            return 1;
        }
        if (label === 1) {
            return 2;
        }
        return group === 1 ? 3 : -1;
    };

    /**
     * The filters to suggest for a text: what matches it, best first; with less than the search's floor,
     * the few with the highest counts to start with.
     *
     * Matching is accent- and case-insensitive on both sides (normalise). Word starts come first, then
     * the larger count, then the order the line draws them in. A filter without a count sorts after
     * every counted one.
     *
     * @param {Array} items The filters on the line: label, group, count (null where none), order.
     * @param {String} text What is in the field.
     * @return {Array} The filters to offer, in order.
     */
    const suggest = (items, text) => {
        const term = normalise(text);
        const count = (item) => (item.count === null ? -1 : item.count);
        if (term.length < MIN_TERM) {
            return items.slice().sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);
        }
        return items.map((item) => ({item: item, rank: rankOf(item, term)}))
            .filter((entry) => entry.rank >= 0)
            .sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)
            .slice(0, MATCH_COUNT)
            .map((entry) => entry.item);
    };

    /**
     * The cards drawn whose names match a text, word starts first, in the order they are drawn.
     *
     * @param {Array} items The cards drawn: label, element.
     * @param {String} text What is in the field.
     * @return {Array} The cards to offer, in order.
     */
    const matchItems = (items, text) => {
        const term = normalise(text);
        if (term.length < MIN_TERM) {
            return [];
        }
        return items.map((item, order) => ({item: item, rank: matchOf(normalise(item.label), term), order: order}))
            .filter((entry) => entry.rank >= 0)
            .sort((a, b) => a.rank - b.rank || a.order - b.order)
            .slice(0, ITEM_COUNT)
            .map((entry) => entry.item);
    };

    /**
     * Whether an element is drawn at all.
     *
     * @param {HTMLElement|null} element The element.
     * @return {Boolean}
     */
    const shown = (element) => Boolean(element) && element.getClientRects().length > 0;

    /**
     * Whether the reader asked for no motion.
     *
     * @return {Boolean}
     */
    const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Arm the filter line of one section.
     *
     * Nothing changes for a toolbar without a line. The field becomes the combobox of the suggestions
     * here, never in the template, because without this module there is no listbox for it to control.
     * A line without a search (enable_search off) still folds, and its panel is still a sheet on the
     * two-line step.
     *
     * The hooks, all of them filters.js's: filters() - the filters a suggestion may apply, as
     * {label, group, count, apply}, each count taken without the typed text, none already in force and
     * none that would leave nothing; chips() - the panel's filters in force, as {label, release};
     * items() - the section's cards drawn, as {label, element}; openItem(element) - take the reader to
     * one; dropTerm() - apply the section's list again now that its field is empty; clearAll() -
     * release every filter of the panel; shown() - how many cards the section shows now.
     *
     * @param {HTMLElement} toolbar The section's toolbar (data-region="dims-line-toolbar").
     * @param {Object} options The hooks.
     * @return {Object|null} The line's API: repaint(), closeSearch(back), closePanel(back) and step(); null without a line.
     */
    const create = (toolbar, options) => {
        const line = toolbar ? toolbar.querySelector(SELECTORS.line) : null;
        if (!line) {
            return null;
        }
        const hooks = Object.assign({
            filters: () => [],
            chips: () => [],
            items: () => [],
            openItem: () => null,
            dropTerm: () => null,
            clearAll: () => null,
            shown: () => 0,
        }, options || {});
        const field = line.querySelector(SELECTORS.search + ' ' + SELECTORS.field);
        const parts = {
            search: line.querySelector(SELECTORS.search),
            eraser: line.querySelector(SELECTORS.eraser),
            opener: line.querySelector(SELECTORS.opener),
            closer: line.querySelector(SELECTORS.closer),
            listbox: line.querySelector(SELECTORS.listbox),
            scope: line.querySelector(SELECTORS.scope),
            star: line.querySelector(SELECTORS.star),
            filters: line.querySelector(SELECTORS.filters),
            toggle: line.querySelector(SELECTORS.toggle),
            panel: line.querySelector(SELECTORS.panel),
            chips: toolbar.querySelector(SELECTORS.chips),
            chiplist: toolbar.querySelector(SELECTORS.chiplist),
            clearall: toolbar.querySelector(SELECTORS.clearall),
        };
        const hassearch = Boolean(field && parts.listbox);

        /** @type {Object} The line's state: its step, the morph, the listbox and the reasons a focus is the reader's. */
        const state = {
            step: '',
            folded: false,
            searching: false,
            open: false,
            from: null,
            quiet: false,
            intent: false,
            tabat: 0,
            width: -1,
            active: -1,
            items: [],
            chips: [],
        };

        /**
         * Move the focus without it counting as the reader's: the field's focus listener stays quiet.
         *
         * @param {HTMLElement|null} element Where the focus goes.
         * @return {void}
         */
        const quietly = (element) => {
            if (!element) {
                return;
            }
            state.quiet = true;
            try {
                element.focus({preventScroll: true});
            } finally {
                state.quiet = false;
            }
        };

        /**
         * Write a text into an element with the term marked where it matches, in the text's own spelling.
         *
         * @param {HTMLElement} element Where the text goes.
         * @param {String} text The text.
         * @param {String} term The normalised term, or the empty string.
         * @return {void}
         */
        const markInto = (element, text, term) => {
            const map = [];
            let folded = '';
            for (let i = 0; i < text.length; i++) {
                const piece = fold(text.charAt(i));
                folded += piece;
                for (let j = 0; j < piece.length; j++) {
                    map.push(i);
                }
            }
            const at = term === '' ? -1 : folded.indexOf(term);
            if (at < 0) {
                element.textContent = text;
                return;
            }
            const start = map[at];
            const end = map[at + term.length - 1] + 1;
            const mark = document.createElement('mark');
            mark.className = CLASSES.mark;
            mark.textContent = text.slice(start, end);
            element.replaceChildren(document.createTextNode(text.slice(0, start)), mark, document.createTextNode(text.slice(end)));
        };

        /**
         * One option of the listbox: its label, its group and its count, all in its accessible name.
         *
         * @param {Object} item The filter or the card.
         * @param {Number} index Its place in the list.
         * @param {String} term The normalised term, or the empty string.
         * @return {HTMLElement}
         */
        const optionOf = (item, index, term) => {
            const option = document.createElement('div');
            option.id = parts.listbox.id + '-' + index;
            option.className = 'dims-line-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.dataset.index = String(index);
            const glyph = document.createElement('span');
            glyph.className = 'dims-line-optionglyph';
            glyph.setAttribute('aria-hidden', 'true');
            const icon = document.createElement('i');
            icon.className = 'fa ' + (item.kind === 'filter' ? 'fa-plus' : 'fa-arrow-right');
            glyph.append(icon);
            const label = document.createElement('span');
            label.className = 'dims-line-optionlabel';
            markInto(label, item.label, term);
            option.append(glyph, label);
            if (item.group) {
                const group = document.createElement('span');
                group.className = 'dims-line-optiongroup';
                group.textContent = item.group;
                option.append(document.createTextNode(' '), group);
            }
            if (typeof item.count === 'number') {
                const count = document.createElement('span');
                count.className = 'dims-line-optioncount';
                count.textContent = String(item.count);
                option.append(document.createTextNode(' '), count);
            }
            return option;
        };

        /**
         * One labelled group of the listbox.
         *
         * @param {String} key Which group: filters or items.
         * @param {String} words Its heading, the server's.
         * @param {HTMLElement[]} options Its options.
         * @return {HTMLElement}
         */
        const groupOf = (key, words, options) => {
            const heading = document.createElement('div');
            heading.id = parts.listbox.id + '-' + key;
            heading.className = 'dims-line-suggesthead';
            heading.setAttribute('role', 'presentation');
            heading.textContent = words || '';
            const group = document.createElement('div');
            group.setAttribute('role', 'group');
            group.setAttribute('aria-labelledby', heading.id);
            group.append(heading, ...options);
            return group;
        };

        /**
         * The bottom of the line's first line of controls, as drawn: the floating layers hang under it.
         *
         * The scope of the two-line step is a line of its own, which the layers cover; and a control
         * slid out of the way has no box to count.
         *
         * @return {Number} A viewport coordinate.
         */
        const lineBottom = () => {
            let bottom = 0;
            Array.from(line.children).forEach((child) => {
                if (child === parts.listbox || (state.step === TWO_LINES && child === parts.scope)) {
                    return;
                }
                const rect = child.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    bottom = Math.max(bottom, rect.bottom);
                }
            });
            return bottom || line.getBoundingClientRect().bottom;
        };

        /**
         * Place a floating layer under the line's real bottom, whatever its containing block.
         *
         * The bottom is measured, never a fixed offset, so a wrapped line, a taller control or the second
         * line of the two-line step never puts a layer over the field or the close button. The Filters
         * panel of the two-line step is a bottom sheet and takes no offset at all.
         *
         * @param {HTMLElement|null} layer The listbox or the Filters panel.
         * @return {void}
         */
        const place = (layer) => {
            if (!layer) {
                return;
            }
            if (layer === parts.panel && state.step === TWO_LINES) {
                layer.style.top = '';
                return;
            }
            const parent = layer.offsetParent;
            if (!parent || !shown(layer)) {
                return;
            }
            const top = parent.getBoundingClientRect().top + parent.clientTop;
            layer.style.top = Math.round(lineBottom() - top + GAP) + 'px';
        };

        /**
         * Place the listbox: under the field at the first two steps, across the line from the third.
         *
         * @return {void}
         */
        const placeList = () => {
            const listbox = parts.listbox;
            const anchored = state.step === 's1' || state.step === 's2';
            listbox.classList.toggle(CLASSES.anchored, anchored);
            if (anchored && parts.search) {
                const base = line.getBoundingClientRect();
                const box = parts.search.getBoundingClientRect();
                const width = Math.min(base.width, Math.max(box.width, MIN_LIST_WIDTH));
                listbox.style.left = Math.round(Math.min(box.left - base.left, base.width - width)) + 'px';
                listbox.style.width = Math.round(width) + 'px';
            } else {
                listbox.style.left = '';
                listbox.style.width = '';
            }
            place(listbox);
        };

        /**
         * Whether the Filters panel is open.
         *
         * @return {Boolean}
         */
        const panelOpen = () => Boolean(parts.panel) && !parts.panel.hidden;

        /**
         * Write the sheet's class on the toolbar while the panel is open at the two-line step.
         *
         * The class raises the toolbar over the cards below it, under the site's fixed navbar, which
         * the sheet never reaches.
         *
         * @return {void}
         */
        const sheet = () => {
            toolbar.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && panelOpen());
        };

        /**
         * Show a row's fades on the sides where it hides something.
         *
         * @param {HTMLElement|null} scroller A row that scrolls sideways.
         * @return {void}
         */
        const fades = (scroller) => {
            if (!scroller) {
                return;
            }
            const max = scroller.scrollWidth - scroller.clientWidth;
            const x = scroller.scrollLeft;
            // Right to left, scrollLeft runs from 0 down to minus the overflow.
            const rtl = window.getComputedStyle(scroller).direction === 'rtl';
            const left = rtl ? max + x : x;
            const right = rtl ? -x : max - x;
            scroller.classList.toggle(CLASSES.fadeleft, max > 1 && left > 2);
            scroller.classList.toggle(CLASSES.faderight, max > 1 && right > 2);
        };

        /**
         * Scroll a half-hidden item of a row fully into view, with room to spare.
         *
         * @param {HTMLElement|null} scroller The row.
         * @param {HTMLElement|null} item The item pressed or focused.
         * @return {void}
         */
        const reveal = (scroller, item) => {
            if (!scroller || !item || !scroller.contains(item) || scroller.scrollWidth <= scroller.clientWidth) {
                return;
            }
            const box = scroller.getBoundingClientRect();
            const rect = item.getBoundingClientRect();
            let dx = 0;
            if (rect.left < box.left + REVEAL_PAD) {
                dx = rect.left - box.left - REVEAL_PAD;
            } else if (rect.right > box.right - REVEAL_PAD) {
                dx = rect.right - box.right + REVEAL_PAD;
            }
            if (dx !== 0) {
                scroller.scrollBy({left: dx, behavior: still() ? 'auto' : 'smooth'});
            }
        };

        /**
         * Put the scope where its step draws it: first on the line, or its own line after every control.
         *
         * Moved in the document and not only in the picture, so the focus order is the order the eye
         * reads; a focused cell is given its focus back once moved.
         *
         * @param {Boolean} down Whether the scope takes a line of its own.
         * @return {void}
         */
        const placeScope = (down) => {
            const scope = parts.scope;
            if (!scope) {
                return;
            }
            const focused = scope.contains(document.activeElement) ? document.activeElement : null;
            const last = parts.listbox || null;
            if (down && scope.nextElementSibling !== last) {
                line.insertBefore(scope, last);
            } else if (!down && line.firstElementChild !== scope) {
                line.insertBefore(scope, line.firstElementChild);
            } else {
                return;
            }
            if (focused) {
                quietly(focused);
            }
        };

        /**
         * Draw the line at one step.
         *
         * @param {String} step One of STEPS.
         * @param {Boolean} folded Whether the field is folded into its icon.
         * @return {void}
         */
        const setStep = (step, folded) => {
            STEPS.forEach((name) => line.classList.toggle(STEP_CLASSES[name], name === step));
            line.classList.toggle(CLASSES.folded, folded && hassearch);
            state.step = step;
            state.folded = folded && hassearch;
            placeScope(step === TWO_LINES);
        };

        /**
         * Whether a control of the line is drawn past either of its edges.
         *
         * While measuring the line does not wrap, so a line that does not fit pushes a control out; the
         * listbox is a layer and never counts.
         *
         * @return {Boolean}
         */
        const overflows = () => {
            const box = line.getBoundingClientRect();
            const style = window.getComputedStyle(line);
            const left = box.left + parseFloat(style.paddingLeft) - 1;
            const right = box.right - parseFloat(style.paddingRight) + 1;
            return Array.from(line.children).some((child) => {
                if (child === parts.listbox) {
                    return false;
                }
                const rect = child.getBoundingClientRect();
                return rect.width > 0 && (rect.right > right || rect.left < left);
            });
        };

        /**
         * Whether the search grows over the line at the step in force.
         *
         * @return {Boolean}
         */
        const morphs = () => hassearch && MORPH_STEPS.indexOf(state.step) >= 0;

        /**
         * The text in the field, once it reaches the search's floor.
         *
         * @return {String}
         */
        const typedTerm = () => (hassearch && normalise(field.value).length >= MIN_TERM ? field.value.trim() : '');

        /**
         * One chip of the row under the line: the remove label, the filter's words, a cross.
         *
         * @param {String} words The filter's words.
         * @return {HTMLElement}
         */
        const chipOf = (words) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'dims-line-chip';
            const remove = document.createElement('span');
            remove.className = 'visually-hidden';
            remove.textContent = parts.chips.dataset.labelRemove || '';
            const label = document.createElement('span');
            label.textContent = words;
            const cross = document.createElement('i');
            cross.className = 'fa fa-times';
            cross.setAttribute('aria-hidden', 'true');
            chip.append(remove, document.createTextNode(' '), label, cross);
            return chip;
        };

        /**
         * Paint the row under the line: a chip per filter of the panel in force, the search's chip while
         * the field is folded away, and "Clear all" from two chips up.
         *
         * The scope and the star are on the line and get no chip. A chip keeps the release filters.js
         * gave for its filter, which its press calls.
         *
         * @return {void}
         */
        const paintChips = () => {
            if (!parts.chips || !parts.chiplist) {
                return;
            }
            const chips = [];
            const term = typedTerm();
            if (term !== '' && state.folded && !state.searching) {
                chips.push({chip: chipOf(term), release: null});
            }
            (hooks.chips() || []).forEach((entry) => {
                chips.push({chip: chipOf(entry.label), release: entry.release});
            });
            state.chips = chips;
            parts.chiplist.replaceChildren(...chips.map((entry) => entry.chip));
            parts.chips.hidden = chips.length === 0;
            if (parts.clearall) {
                parts.clearall.hidden = chips.length < 2;
            }
            fades(parts.chips);
        };

        /**
         * Write on the panel's "Show N" how many cards the section shows now.
         *
         * @return {void}
         */
        const paintShow = () => {
            const count = parts.panel ? parts.panel.querySelector(SELECTORS.showcount) : null;
            if (count) {
                count.textContent = String(hooks.shown() || 0);
            }
        };

        /**
         * Fold the line: try each step in order and keep the first at which it fits.
         *
         * Measured with the line's own box, the open search set aside, every transition off (the
         * measuring class) and every cell of the scope drawn - a cell the counts hide is shown for the
         * measure and hidden again before anything is painted - so the step follows the width and the
         * language and never the numbers of the moment.
         *
         * @return {void}
         */
        const fit = () => {
            if (!line.isConnected) {
                return;
            }
            const searching = state.searching;
            line.classList.add(CLASSES.measuring);
            line.classList.remove(CLASSES.searching);
            const absent = parts.scope && !parts.scope.hidden
                ? Array.from(parts.scope.querySelectorAll(SELECTORS.cell + '[hidden]')) : [];
            absent.forEach((cell) => {
                cell.hidden = false;
            });
            TRIES.some(([step, folded], index) => {
                setStep(step, folded);
                return index === TRIES.length - 1 || !overflows();
            });
            absent.forEach((cell) => {
                cell.hidden = true;
            });
            // An open search is put back before the transitions are, so nothing slides out a second time.
            state.searching = searching && morphs();
            line.classList.toggle(CLASSES.searching, state.searching);
            void line.offsetWidth;
            line.classList.remove(CLASSES.measuring);
            if (parts.opener) {
                parts.opener.setAttribute('aria-expanded', state.searching ? 'true' : 'false');
            }
            sheet();
            place(parts.panel);
            if (state.open) {
                placeList();
            }
            paintChips();
        };

        /**
         * Mark one option of the listbox as the active one, or none.
         *
         * @param {Number} index The option's place, -1 for none.
         * @return {void}
         */
        const activate = (index) => {
            const options = parts.listbox.querySelectorAll(SELECTORS.option);
            state.active = index >= 0 && index < options.length ? index : -1;
            options.forEach((option, at) => {
                option.setAttribute('aria-selected', at === state.active ? 'true' : 'false');
                option.classList.toggle(CLASSES.active, at === state.active);
            });
            if (state.active >= 0) {
                field.setAttribute('aria-activedescendant', options[state.active].id);
                options[state.active].scrollIntoView({block: 'nearest'});
            } else {
                field.removeAttribute('aria-activedescendant');
            }
        };

        /**
         * Take the listbox down.
         *
         * @return {void}
         */
        const closeList = () => {
            if (!hassearch) {
                return;
            }
            state.open = false;
            state.items = [];
            state.active = -1;
            parts.listbox.hidden = true;
            parts.listbox.replaceChildren();
            field.setAttribute('aria-expanded', 'false');
            field.removeAttribute('aria-activedescendant');
        };

        /**
         * Fill the listbox for what is in the field, or take it down when nothing is left to offer.
         *
         * Its headings are the server's: "Start with a filter" while the field is empty, then "Filters"
         * and the section's cards ("Learning plans", "Competencies") for a typed text.
         *
         * @return {void}
         */
        const render = () => {
            if (!state.open) {
                return;
            }
            const typed = normalise(field.value).length >= MIN_TERM;
            const term = typed ? normalise(field.value) : '';
            const offered = suggest((hooks.filters() || []).map((entry, order) => ({
                kind: 'filter',
                label: entry.label,
                group: entry.group || '',
                count: typeof entry.count === 'number' ? entry.count : null,
                apply: entry.apply,
                order: order,
            })), field.value);
            const items = typed ? matchItems(hooks.items() || [], field.value).map((item) => ({
                kind: 'item',
                label: item.label,
                element: item.element,
                group: '',
                count: null,
            })) : [];
            state.items = offered.concat(items);
            state.active = -1;
            field.removeAttribute('aria-activedescendant');
            if (!state.items.length) {
                parts.listbox.hidden = true;
                parts.listbox.replaceChildren();
                field.setAttribute('aria-expanded', 'false');
                return;
            }
            const listbox = parts.listbox;
            const groups = [];
            if (offered.length) {
                const words = typed ? listbox.dataset.labelFilters : listbox.dataset.labelStart;
                groups.push(groupOf('filters', words, offered.map((item, index) => optionOf(item, index, term))));
            }
            if (items.length) {
                groups.push(groupOf('items', listbox.dataset.labelItems,
                    items.map((item, index) => optionOf(item, offered.length + index, term))));
            }
            listbox.replaceChildren(...groups);
            listbox.hidden = false;
            field.setAttribute('aria-expanded', 'true');
            placeList();
        };

        /**
         * Open the search: over the line from the third step, under the field before it, with the suggestions.
         *
         * @param {HTMLElement|null} from The control that opened it, which gets the focus back when it closes.
         * @return {void}
         */
        const openSearch = (from) => {
            if (morphs() && !state.searching) {
                state.searching = true;
                state.from = from || field;
                line.classList.add(CLASSES.searching);
                if (parts.opener) {
                    parts.opener.setAttribute('aria-expanded', 'true');
                }
                paintChips();
            }
            state.open = true;
            if (document.activeElement !== field) {
                quietly(field);
            }
            render();
        };

        /**
         * Close the search and its suggestions; with back, the focus returns to the control that opened it.
         *
         * The focus is moved quietly, so it does not open the search again. A control that is no longer
         * drawn - the field folded away again, the icon at a step that shows the field - hands over to the
         * one that is.
         *
         * @param {Boolean} back Whether the focus goes back to the control that opened the search.
         * @return {void}
         */
        const closeSearch = (back) => {
            closeList();
            if (!state.searching) {
                return;
            }
            state.searching = false;
            line.classList.remove(CLASSES.searching);
            if (parts.opener) {
                parts.opener.setAttribute('aria-expanded', 'false');
            }
            paintChips();
            const from = state.from;
            state.from = null;
            if (back) {
                quietly([from, field, parts.opener].find((control) => shown(control)) || null);
            }
        };

        /**
         * Show the eraser only while the field holds something to erase.
         *
         * @return {void}
         */
        const paintEraser = () => {
            if (parts.eraser && field) {
                parts.eraser.hidden = field.value === '';
            }
        };

        /**
         * Apply one suggestion: a filter with the text dropped first, then applied; a card handed to filters.js.
         *
         * filters.js applies the section's list again with the field empty (dropTerm), then the filter;
         * the search stays open for the next pick, offering what is left.
         *
         * @param {Number} index The option's place.
         * @return {void}
         */
        const choose = (index) => {
            const item = state.items[index];
            if (!item) {
                return;
            }
            if (item.kind === 'item') {
                closeSearch(false);
                hooks.openItem(item.element);
                return;
            }
            field.value = '';
            paintEraser();
            hooks.dropTerm();
            item.apply();
            render();
        };

        /**
         * The field's keys: the arrows walk the suggestions, Enter and Space choose, Escape closes.
         *
         * Space chooses only once an option is active, so a reader typing two words is never interrupted.
         *
         * @param {KeyboardEvent} event The key.
         * @return {void}
         */
        const onFieldKey = (event) => {
            const count = state.items.length;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!state.open) {
                    openSearch(state.from || field);
                    return;
                }
                activate(count ? (state.active + 1) % count : -1);
            } else if (event.key === 'ArrowUp' && state.open) {
                event.preventDefault();
                activate(state.active <= 0 ? -1 : state.active - 1);
            } else if ((event.key === 'Enter' || event.key === ' ') && state.active >= 0) {
                event.preventDefault();
                choose(state.active);
            } else if (event.key === 'Escape' && (state.open || state.searching)) {
                // Before the browser empties a search field on the same key.
                event.preventDefault();
                closeSearch(true);
            }
        };

        /**
         * Open or close the Filters panel; closed with back, the focus returns to the Filters button.
         *
         * @param {Boolean} open Whether the panel opens.
         * @param {Boolean} back Whether a closing panel gives the focus back to its button.
         * @return {void}
         */
        const setPanel = (open, back) => {
            if (!parts.panel || !parts.toggle) {
                return;
            }
            parts.panel.hidden = !open;
            parts.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                closeSearch(false);
                paintShow();
                place(parts.panel);
            } else if (back) {
                quietly(parts.toggle);
            }
            sheet();
        };

        /**
         * Escape on the line: the open panel closes back to its button, the search grown over the line
         * closes back to its opener.
         *
         * @param {KeyboardEvent} event The key.
         * @return {void}
         */
        const onLineKey = (event) => {
            if (event.key !== 'Escape' || event.defaultPrevented || !(event.target instanceof Element)) {
                return;
            }
            if (panelOpen()) {
                event.preventDefault();
                setPanel(false, true);
                return;
            }
            if (state.searching && event.target !== field) {
                event.preventDefault();
                closeSearch(true);
            }
        };

        /**
         * The slash key: to the search, when the line is on screen and the focus is in no field.
         *
         * The first line on screen takes it; the next one sees it taken (defaultPrevented).
         *
         * @param {KeyboardEvent} event The key.
         * @return {void}
         */
        const onSlash = (event) => {
            const active = document.activeElement;
            if (active && (active.isContentEditable || active.closest('input, textarea, select'))) {
                return;
            }
            const rect = line.getBoundingClientRect();
            if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {
                return;
            }
            event.preventDefault();
            openSearch(state.folded && !state.searching ? parts.opener : field);
        };

        /**
         * Empty the field and apply the section's list again, as the eraser does.
         *
         * @return {void}
         */
        const eraseTerm = () => {
            if (!field) {
                return;
            }
            field.value = '';
            paintEraser();
            hooks.dropTerm();
        };

        /**
         * Where a focus should go once the control holding it is gone: the field, or its icon where the
         * field is folded away, or the Filters button on a line without a search.
         *
         * @return {HTMLElement|null}
         */
        const focusTarget = () => {
            if (!field) {
                return parts.toggle;
            }
            if (shown(field) || !shown(parts.opener)) {
                return field;
            }
            return parts.opener;
        };

        /**
         * A chip of the row pressed: the filter it stands for released, the focus kept in the row.
         *
         * @param {HTMLElement} chip The chip.
         * @return {void}
         */
        const onChip = (chip) => {
            const entries = state.chips;
            const at = entries.findIndex((entry) => entry.chip === chip);
            if (at < 0) {
                return;
            }
            const entry = entries[at];
            if (entry.release) {
                entry.release();
            } else {
                eraseTerm();
            }
            paintChips();
            const left = state.chips;
            const next = left[Math.min(at, left.length - 1)];
            quietly(next ? next.chip : focusTarget());
        };

        /**
         * "Clear all": every filter of the panel released and the field emptied, the focus to the search.
         *
         * @return {void}
         */
        const onClearAll = () => {
            hooks.clearAll();
            eraseTerm();
            paintChips();
            quietly(focusTarget());
        };

        /**
         * Wire the field, the eraser, the search's two buttons and the listbox.
         *
         * The field opens the search on the reader's focus only - a press on it or a Tab into it - and
         * never on a focus moved there by code, or quietly here (the guard that keeps a closed search
         * from opening again as its focus comes back).
         *
         * @return {void}
         */
        const listenSearch = () => {
            field.addEventListener('pointerdown', () => {
                state.intent = true;
            });
            field.addEventListener('focusin', () => {
                const intent = state.intent || Date.now() - state.tabat < TAB_INTENT;
                state.intent = false;
                if (state.quiet || !intent) {
                    return;
                }
                openSearch(field);
            });
            field.addEventListener('click', () => {
                if (!state.open) {
                    openSearch(state.from || field);
                }
            });
            field.addEventListener('input', () => {
                paintEraser();
                if (state.open) {
                    render();
                } else if (document.activeElement === field) {
                    openSearch(field);
                }
            });
            field.addEventListener('keydown', onFieldKey);
            if (parts.eraser) {
                parts.eraser.addEventListener('click', () => {
                    eraseTerm();
                    quietly(field);
                    render();
                });
            }
            if (parts.opener) {
                parts.opener.addEventListener('click', () => openSearch(parts.opener));
            }
            if (parts.closer) {
                parts.closer.addEventListener('click', () => closeSearch(true));
            }
            // A press on an option keeps the focus in the field, which is the combobox.
            parts.listbox.addEventListener('mousedown', (event) => event.preventDefault());
            parts.listbox.addEventListener('click', (event) => {
                const option = event.target instanceof Element ? event.target.closest(SELECTORS.option) : null;
                if (option) {
                    choose(Number(option.dataset.index));
                }
            });
            line.addEventListener('focusout', (event) => {
                const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
                if (next === field || (next && parts.listbox.contains(next))) {
                    return;
                }
                if (next && line.contains(next)) {
                    // Still on the line - its close button, a control beside the field: the suggestions go.
                    closeList();
                    return;
                }
                // The focus left the line: the search closes where it stands, and nothing it hid keeps the focus.
                closeSearch(false);
            });
        };

        /**
         * Wire the chips row: a chip releases its filter, "Clear all" every one; the row reveals and fades.
         *
         * The reveal answers the press (click) and the focus, once the press has landed, so a row that
         * scrolls under a finger never hands the press to the item beside it.
         *
         * @return {void}
         */
        const listenRows = () => {
            const row = parts.chips;
            if (!row) {
                return;
            }
            const itemOf = (target) => (target instanceof Element ? target.closest('button') : null);
            row.addEventListener('scroll', () => fades(row), {passive: true});
            row.addEventListener('focusin', (event) => reveal(row, itemOf(event.target)));
            row.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : null;
                const chip = target ? target.closest('.dims-line-chip') : null;
                if (chip) {
                    onChip(chip);
                } else if (target && target.closest(SELECTORS.clearall)) {
                    onClearAll();
                } else {
                    reveal(row, itemOf(event.target));
                }
            });
        };

        /**
         * Wire the panel - its button, "Show N", the press outside, the sheet - Escape and the slash key.
         *
         * @return {void}
         */
        const listenLayers = () => {
            if (parts.toggle) {
                parts.toggle.addEventListener('click', () => setPanel(!panelOpen(), false));
            }
            const show = parts.panel ? parts.panel.querySelector(SELECTORS.show) : null;
            if (show) {
                // "Show N" closes the panel on the list it describes, back to the Filters button.
                show.addEventListener('click', () => setPanel(false, true));
            }
            line.addEventListener('keydown', onLineKey);
            document.addEventListener('pointerdown', (event) => {
                const target = event.target instanceof Node ? event.target : null;
                if (!target) {
                    return;
                }
                if ((state.open || state.searching) && !line.contains(target)) {
                    closeSearch(false);
                }
                // A panel over the cards - and the sheet, with nothing beside it to press again - closes on a press outside it.
                if (panelOpen() && !parts.panel.contains(target) && !parts.toggle.contains(target)) {
                    setPanel(false, false);
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Tab') {
                    state.tabat = Date.now();
                } else if (hassearch && event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey
                        && !event.defaultPrevented) {
                    onSlash(event);
                }
            });
        };

        /**
         * Fold the line again once its width changed, after the frame, held as pending JavaScript.
         *
         * @return {void}
         */
        const refit = () => {
            const pending = new Pending('block_dimensions/filter_line:fit');
            window.requestAnimationFrame(() => {
                fit();
                pending.resolve();
            });
        };

        /**
         * Bring the line in line with the list filters.js has just applied: its step (a pill, the star or
         * Filters came back or went), its chips, its eraser, "Show N" and its suggestions.
         *
         * @return {void}
         */
        const repaint = () => {
            paintEraser();
            paintShow();
            fit();
            if (hassearch) {
                render();
            }
        };

        if (hassearch) {
            const label = field.labels && field.labels.length ? field.labels[0].textContent.trim() : '';
            parts.listbox.setAttribute('role', 'listbox');
            parts.listbox.setAttribute('aria-label', label);
            field.setAttribute('role', 'combobox');
            field.setAttribute('aria-autocomplete', 'list');
            field.setAttribute('aria-expanded', 'false');
            field.setAttribute('aria-controls', parts.listbox.id);
            listenSearch();
        }
        listenLayers();
        listenRows();
        paintEraser();
        paintShow();
        fit();
        // The line's own width, never the viewport's: a drawer opening changes the one and not the other.
        new ResizeObserver((entries) => {
            const width = Math.round(entries[0].contentRect.width);
            if (width !== state.width) {
                state.width = width;
                refit();
            }
        }).observe(line);
        // A webfont arriving late changes every width on the line.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => refit()).catch(() => null);
        }

        return {
            repaint: repaint,
            closeSearch: closeSearch,
            closePanel: (back) => setPanel(false, back),
            step: () => state.step,
        };
    };

    return {
        create: create,
        normalise: normalise,
        suggest: suggest,
        matchItems: matchItems,
    };
});

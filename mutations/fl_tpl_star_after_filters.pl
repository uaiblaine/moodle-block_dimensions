# Put the star after Filters: the contract's order is search, star, Filters.
s/(        <button[^\n]*data-region="dims-line-star"[^\n]*\n)(        <div class="dims-line-filters.*?\n        <\/div>\n)/$2$1/s;

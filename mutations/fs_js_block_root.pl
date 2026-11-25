# Handle the block's own stars too, so a page with both toggles twice.
s/if \(!button \|\| button\.closest\(BLOCK_ROOT\)\) \{/if (!button) {/;

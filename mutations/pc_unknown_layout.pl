# Accept any layout string.
s/if \(\$layout !== self::LAYOUT_VERTICAL && \$layout !== self::LAYOUT_HORIZONTAL\) \{/if (false) {/;

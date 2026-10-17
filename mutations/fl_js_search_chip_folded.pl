# Draw the text's chip beside a visible field: the same filter shows twice.
s/if \(term !== '' && state\.folded && !state\.searching\) \{/if (term !== '') {/;

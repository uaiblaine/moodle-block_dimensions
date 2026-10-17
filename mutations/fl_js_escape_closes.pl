# Leave Escape in the field to the browser: it empties the field and the search stays open.
s/\} else if \(event\.key === 'Escape' && \(state\.open \|\| state\.searching\)\) \{/} else if (event.key === 'Esc' && (state.open || state.searching)) {/;

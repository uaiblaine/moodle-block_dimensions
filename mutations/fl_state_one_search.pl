# Search every card with the plans' text: a plan search empties the competencies.
s/const term = state\.normalizedSearch\[type\];/const term = state.normalizedSearch.plan;/;

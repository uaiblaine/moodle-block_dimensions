# Leave the open panel to Escape on the line: it stays open over the cards.
s/if \(panelOpen\(\)\) \{\n                event\.preventDefault\(\);/if (false) {\n                event.preventDefault();/;

# Draw the plans' line above its heading.
s/(    \{\{#showsectionheaders\}\}\n    <h3 id="dims-section-plan-.*?\{\{\/showsectionheaders\}\}\n)(    \{\{#planline\}\}\n    \{\{> block_dimensions\/filter_line \}\}\n    \{\{\/planline\}\}\n)/$2$1/s;

# Leave the field without its combobox role: a screen reader never hears of the suggestions.
s/^            field\.setAttribute\('role', 'combobox'\);\n//m;

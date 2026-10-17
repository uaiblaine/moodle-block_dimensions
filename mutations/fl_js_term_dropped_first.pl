# Apply the filter before the text is dropped: the filter is applied over a search the reader is leaving.
s/hooks\.dropTerm\(\);\n            item\.apply\(\);/item.apply();\n            hooks.dropTerm();/;

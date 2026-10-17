# Leave Filters saying it is closed while its panel is open.
s/parts\.toggle\.setAttribute\('aria-expanded', open \? 'true' : 'false'\);/parts.toggle.setAttribute('aria-expanded', 'false');/;

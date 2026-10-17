# Offer "Clear all" beside a single chip, which its own cross already clears.
s/parts\.clearall\.hidden = chips\.length < 2;/parts.clearall.hidden = chips.length < 1;/;

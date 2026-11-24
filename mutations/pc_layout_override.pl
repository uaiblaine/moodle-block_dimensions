# Let the site setting win over the layout the caller asked for.
s/\$ishorizontal = \$layout === plan_cards::LAYOUT_HORIZONTAL;/\$ishorizontal = plan_cards::site_layout() === plan_cards::LAYOUT_HORIZONTAL;/;

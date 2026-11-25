# Read the favourites while the block's favourites are disabled.
s/!\$viewonly && self::is_favourites_enabled\(\);/!\$viewonly;/;

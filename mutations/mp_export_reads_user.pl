# Export the favourites of the user running the export instead of the request's subject.
s/\$userid = \$contextlist->get_user\(\)->id;/\$userid = (int) \$GLOBALS['USER']->id;/;

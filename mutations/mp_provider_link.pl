# Drop the link to the core Favourites subsystem from the metadata.
s/add_subsystem_link\(\n(\s*)'core_favourites'/add_subsystem_link(\n$1'core_files'/;

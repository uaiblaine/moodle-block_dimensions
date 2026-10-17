# Take the Filters word out of the document instead of the picture: the button loses its name.
s/(    white-space: nowrap;\n    )clip-path: inset\(50%\);(\n\}\n\n\.block_dimensions \.dims-line--s2 \.dims-line-filterstoggle)/${1}display: none;$2/;

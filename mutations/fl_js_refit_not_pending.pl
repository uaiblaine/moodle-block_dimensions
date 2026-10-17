# Refold without holding the frame as pending JavaScript: Behat reads a line still folding.
s/const pending = new Pending\('block_dimensions\/filter_line:fit'\);/const pending = {resolve: () => null};/;

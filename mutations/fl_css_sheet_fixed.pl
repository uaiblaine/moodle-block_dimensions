# Leave the two-line step's panel a popover: a narrow screen cuts it at its edge.
s/(\.block_dimensions \.dims-line--s4 \.dims-line-panel \{\n    position: )fixed;/${1}absolute;/;

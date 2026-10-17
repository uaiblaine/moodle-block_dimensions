# A text typed in one section releases the other section's star too.
s/(!== before\[type\]\) \{\n                )state\.favouriteFilterActive\[type\] = false;/${1}state.favouriteFilterActive.plan = false;\n                state.favouriteFilterActive.competency = false;/;

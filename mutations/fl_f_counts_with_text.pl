# Count a suggested tag with the typed text: the count says nothing about the list once it is chosen.
s/const count = countWith\(state, type, changes, false\);/const count = countWith(state, type, changes, true);/;

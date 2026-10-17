# Leave the last try to its measure: a line too narrow for every step ends on the last one tried by chance.
s/return index === TRIES\.length - 1 \|\| !overflows\(\);/return !overflows();/;

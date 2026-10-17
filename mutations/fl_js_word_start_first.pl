# Rank by count before the word start: a big group inside a word outranks the word the reader typed.
s/\.sort\(\(a, b\) => a\.rank - b\.rank \|\| count\(b\.item\) - count\(a\.item\) \|\| a\.item\.order - b\.item\.order\)/.sort((a, b) => count(b.item) - count(a.item) || a.rank - b.rank || a.item.order - b.item.order)/;

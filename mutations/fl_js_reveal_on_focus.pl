# Leave a half-hidden chip half hidden when the keyboard reaches it.
s/^            row\.addEventListener\('focusin', \(event\) => reveal\(row, itemOf\(event\.target\)\)\);\n//m;

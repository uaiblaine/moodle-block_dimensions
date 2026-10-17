# Offer a tag that would leave nothing.
s/(changes\[group\.field\] = value;\n                            const count = countWith\(state, type, changes, false\);\n                            )if \(count > 0\) \{/${1}if (count >= 0) {/;

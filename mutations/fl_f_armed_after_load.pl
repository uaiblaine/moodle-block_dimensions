# Arm the lines after the first load is sent: a quick response renders lines nothing drives.
s/armLines\(container, state, safeOptions\);\n        loadData\(container, state, safeOptions\);/loadData(container, state, safeOptions);\n        armLines(container, state, safeOptions);/;

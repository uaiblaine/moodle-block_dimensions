# Leave a control slid out visible, and so in the focus order.
s/(    opacity: 0;\n    )visibility: hidden;(\n    pointer-events: none;)/${1}visibility: visible;$2/;

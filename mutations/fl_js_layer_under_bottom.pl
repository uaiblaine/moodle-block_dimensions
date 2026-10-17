# Hang a layer at a fixed offset: a taller control or a wrapped line puts the panel over the field.
s/layer\.style\.top = Math\.round\(lineBottom\(\) - top \+ GAP\) \+ 'px';/layer.style.top = 'calc(100% + 6px)';/;

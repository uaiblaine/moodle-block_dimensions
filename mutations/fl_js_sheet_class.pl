# Never write the sheet class: the open sheet sits under the cards that follow the line.
s/toolbar\.classList\.toggle\(CLASSES\.sheet, state\.step === TWO_LINES && panelOpen\(\)\);/toolbar.classList.toggle(CLASSES.sheet, false);/;

cp *.svg ../illu
for FILE in ../illu/*.svg; do inkscape --batch-process --actions="select-all;object-to-path;export-filename:../illu/$FILE;export-do;quit-immediate;" $FILE ;done

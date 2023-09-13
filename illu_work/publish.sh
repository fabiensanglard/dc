cp *.svg ../illuset
for FILE in ../illu/*.svg; do inkscape --batch-process --actions='EditSelectAll;SelectionUnGroup;EditSelectAll;ObjectToPath;FileSave;FileQuit' $FILE ;done

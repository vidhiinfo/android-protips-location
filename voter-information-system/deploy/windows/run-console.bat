@echo off
rem Starts the application in this console window (for a first test). Close the window or press Ctrl+C to stop.
rem Put voter-information-system.jar and the config folder in the same folder as this file.
cd /d "%~dp0"
java -Dfile.encoding=UTF-8 -Duser.timezone=Asia/Kolkata -jar voter-information-system.jar
pause

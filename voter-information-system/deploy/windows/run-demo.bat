@echo off
rem Try the application WITHOUT MySQL: in-memory database with ~2,000 sample voters (data is lost when you close the window).
rem Then open http://localhost:8080 and log in as admin / admin@123.
cd /d "%~dp0"
java -Dfile.encoding=UTF-8 -jar voter-information-system.jar --spring.profiles.active=demo --server.address=127.0.0.1 --spring.config.location=classpath:/
pause

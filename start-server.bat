@echo off
cd /d "%~dp0"
start "" "http://127.0.0.1:8000/"
"D:\Downloads\php-8.3.31-Win32-vs16-x64\php.exe" -S 127.0.0.1:8000 -t . router.php
pause

@echo off
cd /d "%~dp0"
:loop
"D:\Downloads\php-8.3.31-Win32-vs16-x64\php.exe" telegram-poll.php
timeout /t 2 >nul
goto loop

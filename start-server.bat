@echo off
cd /d "%~dp0"
if exist "D:\Downloads\php-8.3.31-Win32-vs16-x64\php.exe" (
    start "" "http://127.0.0.1:8000/"
    "D:\Downloads\php-8.3.31-Win32-vs16-x64\php.exe" -S 127.0.0.1:8000 -t . router.php
    goto end
)
where php >nul 2>nul
if errorlevel 1 (
    echo PHP not found. Install PHP and add php.exe to PATH.
    pause
    exit /b 1
)
start "" "http://127.0.0.1:8000/"
php -S 127.0.0.1:8000 -t . router.php
:end
pause

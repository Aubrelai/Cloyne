@echo off
REM Cloyne: pull the latest code, update the database, run checks, and start the app.
REM Double-click this file in File Explorer, or run .\update.bat in the VS Code terminal.

cd /d "%~dp0"

echo.
echo === 1/5  Getting the latest code from GitHub ===
git pull
if errorlevel 1 goto failed

echo.
echo === 2/5  Installing any new Laravel packages ===
call composer install --no-interaction
if errorlevel 1 goto failed

echo.
echo === 3/5  Updating the database ===
if not exist .env copy .env.example .env >nul && php artisan key:generate
if not exist database\database.sqlite type nul > database\database.sqlite
php artisan migrate --force
if errorlevel 1 goto failed
php artisan db:seed --force
if errorlevel 1 goto failed

echo.
echo === 4/5  Running checks ===
php artisan test
if errorlevel 1 goto failed

echo.
echo === 5/5  Starting Cloyne ===
echo Open http://127.0.0.1:8000 in your browser. Close this window to stop the app.
start "" http://127.0.0.1:8000
php artisan serve
goto end

:failed
echo.
echo *** Something went wrong above. Take a screenshot of this window and send it to Claude. ***
pause

:end

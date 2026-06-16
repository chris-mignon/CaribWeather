@echo off
setlocal

for /f "tokens=2,*" %%A in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path 2^>nul') do set "MACHINE_PATH=%%B"
for /f "tokens=2,*" %%A in ('reg query "HKCU\Environment" /v Path 2^>nul') do set "USER_PATH=%%B"
set "PATH=%MACHINE_PATH%;%USER_PATH%"

set "PHP_BIN=php"
where php >nul 2>nul
if errorlevel 1 set "PHP_BIN=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

if not exist artisan (
    echo Run this file from the CaribWeather project folder.
    pause
    exit /b 1
)

echo Starting CaribWeather at http://127.0.0.1:8000
echo Keep this window open while using the app.
"%PHP_BIN%" artisan serve --host=127.0.0.1 --port=8000 --no-reload

if errorlevel 1 (
    echo.
    echo Laravel did not start. Copy the error above and send it here.
    pause
)

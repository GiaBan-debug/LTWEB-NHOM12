@echo off
setlocal
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start.ps1" %*
set "demoExitCode=%errorlevel%"
if not "%demoExitCode%"=="0" (
    echo.
    echo Khong khoi dong duoc website. Xem loi ben tren.
) else (
    echo.
    echo Neu website dang chay, mo dia chi localhost duoc in ben tren.
)
echo.
pause
exit /b %demoExitCode%

@echo off
REM Windows Task Scheduler Setup Script
REM This creates a scheduled task to run Laravel scheduler every minute

echo Creating Windows Scheduled Task for Laravel Scheduler...

REM Get the current directory
set "PROJECT_PATH=%~dp0"

REM Create the scheduled task
schtasks /Create /SC MINUTE /MO 1 /TN "Laravel_Scheduler_Steren_App" /TR "php %PROJECT_PATH%artisan schedule:run" /F /RU "%USERNAME%"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ Scheduled task created successfully!
    echo.
    echo Task Name: Laravel_Scheduler_Steren_App
    echo Runs: Every 1 minute
    echo Command: php artisan schedule:run
    echo.
    echo To verify, open Task Scheduler and look for "Laravel_Scheduler_Steren_App"
    echo.
) else (
    echo.
    echo ✗ Failed to create scheduled task
    echo Please run this script as Administrator
    echo.
)

pause


@echo off
echo Starting PHP script with explicit php.ini... >> C:\IQSync\sync_activity_log.txt
echo %date% %time% >> C:\IQSync\sync_activity_log.txt
C:\PHP\php.exe -c C:\PHP\php.ini C:\IQSync\sync_stock.php >> C:\IQSync\sync_activity_log.txt 2>&1
echo Script execution finished. >> C:\IQSync\sync_activity_log.txt
echo -------------------------------------------------- >> C:\IQSync\sync_activity_log.txt
REM You can remove the 'pause' if this is only for scheduled tasks,
REM or keep it if you also run the .bat manually for testing.
pause
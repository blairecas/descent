@echo off
php -f create_tables.php
if %ERRORLEVEL% NEQ 0 ( exit /b )

..\..\php5\php.exe -c ..\..\php5\ -f create_tables.php
if %ERRORLEVEL% NEQ 0 ( exit /b )

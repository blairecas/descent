@echo off
echo.
echo ===========================================================================
echo Cleanup
echo ===========================================================================
del _descnt.lst
del descnt.sav

echo.
echo ===========================================================================
echo Compiling DESCNT.MAC
echo ===========================================================================
rt11 macro descnt.mac/list:descnt.lst
rem ..\..\macro11\macro11.exe -m ..\..\macro11\sysmac.sml -l _descnt.lst -o descnt.obj descnt.mac
if %ERRORLEVEL% NEQ 0 ( exit /b )

echo.
echo ===========================================================================
echo Linking DESCNT.OBJ
echo ===========================================================================
rt11 link descnt
if %ERRORLEVEL% NEQ 0 ( exit /b )

echo.
echo ===========================================================================
echo Cleanup, Writing to .DSK
echo ===========================================================================
del descnt.obj
rt11 copy/predelete descnt.sav ld0:descnt.sav
move /y DESCNT.LST _descnt.lst >nul
move /y DESCNT.SAV release\descnt.sav >nul
echo.

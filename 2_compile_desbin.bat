@echo off
echo.
echo ===========================================================================
echo Compiling graphics
echo ===========================================================================
..\..\php5\php.exe -c ..\..\php5\ -f conv_spr.php
if %ERRORLEVEL% NEQ 0 ( exit /b )
move /y graphics\inc_cpu_sprites.mac inc_cpu_sprites.mac >NUL
move /y graphics\inc_ppu_sprites.mac inc_ppu_sprites.mac >NUL

echo.
echo ===========================================================================
echo Compiling DESCNT_CPU.MAC
echo ===========================================================================
..\..\php5\php.exe -c ..\..\php5\ -f preprocess.php descnt_cpu.mac
if %ERRORLEVEL% NEQ 0 ( exit /b )
..\..\macro11\macro11.exe -ysl 32 -yus -m ..\..\macro11\sysmac.sml -l _descnt_cpu.lst _descnt_cpu.mac
if %ERRORLEVEL% NEQ 0 ( exit /b )

echo.
echo ===========================================================================
echo Compiling DESCNT_PPU.MAC
echo ===========================================================================
..\..\php5\php.exe -c ..\..\php5\ -f conv_level.php
..\..\php5\php.exe -c ..\..\php5\ -f preprocess.php descnt_ppu.mac
if %ERRORLEVEL% NEQ 0 ( exit /b )
..\..\macro11\macro11.exe -ysl 32 -yus -m ..\..\macro11\sysmac.sml -l _descnt_ppu.lst _descnt_ppu.mac
if %ERRORLEVEL% NEQ 0 ( exit /b )

echo.
echo ===========================================================================
echo Creating *.DAT RAM images
echo ===========================================================================
..\..\php5\php.exe -c ..\..\php5\ -f gen_overlay.php
if %ERRORLEVEL% NEQ 0 ( exit /b )

echo.
echo ===========================================================================
echo Compressing, Aligning to 512-bytes size, Updating .DSK
echo ===========================================================================
..\packers\lzsa3.exe _descnt_cpu.dat descpu.dat
..\packers\lzsa3.exe _descnt_ppu.dat desppu.dat
..\packers\lzsa3.exe _descnt_r12.dat desr12.dat
..\packers\lzsa3.exe _descnt_r00.dat desr00.dat
rem -- align files with 512-bytes (block)
powershell -Command "& { $f = new-object System.IO.FileStream descpu.dat, Open, ReadWrite; if (($f.Length %% 512) -ne 0) { $f.SetLength($f.Length + 512 - ($f.Length %% 512)); } $f.Close(); }"
powershell -Command "& { $f = new-object System.IO.FileStream desppu.dat, Open, ReadWrite; if (($f.Length %% 512) -ne 0) { $f.SetLength($f.Length + 512 - ($f.Length %% 512)); } $f.Close(); }"
powershell -Command "& { $f = new-object System.IO.FileStream desr12.dat, Open, ReadWrite; if (($f.Length %% 512) -ne 0) { $f.SetLength($f.Length + 512 - ($f.Length %% 512)); } $f.Close(); }"
powershell -Command "& { $f = new-object System.IO.FileStream desr00.dat, Open, ReadWrite; if (($f.Length %% 512) -ne 0) { $f.SetLength($f.Length + 512 - ($f.Length %% 512)); } $f.Close(); }"
rem -- writing to .DSK file
rem rt11 copy/predelete descpu.dat ld0:descpu.dat
rem rt11 copy/predelete desppu.dat ld0:desppu.dat
rem rt11 copy/predelete desr12.dat ld0:desr12.dat
rem rt11 copy/predelete desr00.dat ld0:desr00.dat
..\..\macro11\rt11dsk.exe d descnt.dsk descpu.dat >NUL
..\..\macro11\rt11dsk.exe a descnt.dsk descpu.dat >NUL
..\..\macro11\rt11dsk.exe d descnt.dsk desppu.dat >NUL
..\..\macro11\rt11dsk.exe a descnt.dsk desppu.dat >NUL
..\..\macro11\rt11dsk.exe d descnt.dsk desr12.dat >NUL
..\..\macro11\rt11dsk.exe a descnt.dsk desr12.dat >NUL
..\..\macro11\rt11dsk.exe d descnt.dsk desr00.dat >NUL
..\..\macro11\rt11dsk.exe a descnt.dsk desr00.dat >NUL
move /y descpu.dat release\descpu.dat >NUL
move /y desppu.dat release\desppu.dat >NUL
move /y desr12.dat release\desr12.dat >NUL
move /y desr00.dat release\desr00.dat >NUL
echo.
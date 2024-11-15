@echo off
php -f conv_spr.php
if %ERRORLEVEL% NEQ 0 ( exit /b )
move /y graphics\inc_cpu_sprites.mac inc_cpu_sprites.mac
move /y graphics\inc_ppu_sprites.mac inc_ppu_sprites.mac

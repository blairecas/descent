DESCNT
======

Simple game prototype for soviet UKNC (Elektronika MS-0511) computer from 80-s
Computer on wiki: https://en.wikipedia.org/wiki/UKNC
Emulator UKNCBTL repository: https://github.com/nzeemin/ukncbtl

HOWTO RUN IT
============

Copy *.dat, descnt.sav from release folder to disk image for emulator UKNCBTL.
Use RT-11dsk.wcx plugin for Total Commander to copy them into .dsk disk image
file. Run it in RT-11 (must run from DK device, so game can locate .dat files):
For MZ1 device it can be like this:
ASS MZ1 DK
RU DESCNT

Or use descnt.dsk disk image provided here. Attach it to emulator and it will
autorun this game prototype.


COMPILATION
===========
1_compile_des.bat - will try to make descnt.sav loader
2_compile_desbin.bat - will try to make *.dat files (they are RAM/VRAM data for
both processors)

Utilities needed:
php.exe     - well, it's PHP 5 (I think 7 will also work) ^_^
rt11.exe    - PDP-11 emulator for win32, already here with system.dsk
macro11.exe - http://retrocmp.com/tools/macro-11-on-windows
lzsa3.exe   - get from https://github.com/imachug/lzsa3/ release
rt11dsk.exe - get from https://github.com/nzeemin/ukncbtl-utils/ release
              (can use rt11.exe to copy files to .dsk though,  as rt11dsk 
	      will not squeeze empty disk image space)

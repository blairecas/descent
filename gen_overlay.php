<?php

    // TLDR: relative ' addrs are not linking right! It will NOT work with a regular macro11
    // only works with .ASECT and non-standard macro11.exe listing files!!!
    // (and yes I am lazy to do smth right here, it works and ok..)
    
    $prefix = '_descnt_';

    $allRAM = Array(
	'cpu'      => Array(),
	'cpumax'   => 0377777,	// CPU maximal addr (CPU RAM + VRAM planes 1,2)
	'ppu'	   => Array(),
	'ppumax'   => 0177777	// PPU ending addr (PPU RAM + VRAM plane 0)
    );

    $lnum = 0;

    echo "\n";
    $current_line = "";
    ProcessFile('cpu');
    ProcessFile('ppu');

    // write CPU data file
    $fname = $prefix . 'cpu.dat';
    $fout = fopen($fname, "w");
    WriteSection($fout, 'cpu', 0, 0156000);
    fclose($fout);

    $fname = $prefix . 'ppu.dat';
    $fout = fopen($fname, "w");
    WriteSection($fout, 'ppu', 0, 0076000);
    fclose($fout);

    $fname = $prefix . 'r12.dat';
    $fout = fopen($fname, "w");
    WriteSection($fout, 'cpu', 0256700, 0400000);
    fclose($fout);

    $fname = $prefix . 'r00.dat';
    $fout = fopen($fname, "w");
    WriteSection($fout, 'ppu', 0127340, 0200000);
    fclose($fout);
    
////////////////////////////////////////////////////////////////////////////////

$ssline0 = "";
$ssline1 = "";
$ssline2 = "";

function exit_with_error ($s)
{
    global $ssline0, $ssline1, $ssline2;
    echo "$s\n";
    echo $ssline0;
    echo $ssline1;
    echo $ssline2;
    exit(1);
}


function ProcessFile ( $processor )
{
    global $prefix, $allRAM;
    global $current_line;    
    $fname = strtoupper($prefix . $processor . '.lst');    
    echo "processing $fname\n";
    $lcount = 0;
    $fin = fopen($fname, "r");
    if ($fin === false) {
        echo "ERROR: file $fname not found\n";
        exit(1);
    }    
    while (!feof($fin))
    {
        $current_line = fgets($fin);
        $b = UseLine($current_line, $processor);
        if (!$b) break;
        $lcount++;
    }
    fclose($fin);    
    echo "used $lcount lines\n";
}


function UseLine ( $sline, $processor )
{
    global $lnum;
    global $ssline0, $ssline1, $ssline2;

    // rotate history
    $ssline0 = $ssline1; $ssline1 = $ssline2; $ssline2 = $sline;

    // empty string?
    $sline = rtrim($sline); if (strlen($sline)==0) return true;
    // assume 'Symbol table' as end
    if (strcasecmp($sline, "Symbol table") == 0) return false;
    // first character
    $fc = ord($sline[0]);
    // it's a page description - skip it
    if ($fc == 0x0C) return true;
    // no line number
    $lnum = 0;
    if ($fc == 0x09) $sline = substr($sline, 1);
                else $sline = GetLineNumber($sline, $lnum);
    // try to get addr
    $gAddr = 0; $type0 = -1;
    $sline = GetOctal($sline, $gAddr, $type0);
    if ($type0 < 0) exit_with_error("ERROR: in ADDR on $lnum");
    // now trying to get three octals
    $oct1 = 0; $type1 = -1; $sline = GetOctal($sline, $oct1, $type1);
    $oct2 = 0; $type2 = -1; $sline = GetOctal($sline, $oct2, $type2);
    $oct3 = 0; $type3 = -1; $sline = GetOctal($sline, $oct3, $type3);
    // error when converting (e.g. got 000000G global)
    if ($type1 < 0 || $type2 < 0 || $type3 < 0) exit_with_error("ERROR: in DATA on $lnum");
    // empty line
    if ($type1==0 && $type2==0 && $type3==0) return true;
    // no first octal?
    if ($type1==0 && ($type2>0 || $type3>0)) exit_with_error("ERROR: no first octal in DATA on $lnum");
    // no second octal
    if ($type2==0 && $type3>0) exit_with_error("ERROR: no second octal in DATA on $lnum");
    // first octal can't be relative
    if ($type1==3) exit_with_error("ERROR: first octal can't be relative on $lnum\n");
    // convert relatives for 2nd octal
    $next_addr = $gAddr + 2;
    if ($type2==1) $next_addr++;
    if ($type2==2 || $type2==3) $next_addr += 2;
    if ($type2==3) {
	echo "REL2: ".decoct($oct2)." - ".decoct($next_addr);
	$oct2 = $oct2 - $next_addr;
	if ($oct2<0) $oct2 = (0x10000 + $oct2) & 0xFFFF;
	echo " = ".decoct($oct2)." on $lnum\n";
    }
    // .. for 3rd octal
    if ($type3==1) $next_addr++;
    if ($type3==2 || $type3==3) $next_addr += 2;
    if ($type3==3) {
	echo "REL3: ".decoct($oct3)." ".decoct($next_addr);
	$oct3 = $oct3 - $next_addr;
	if ($oct3<0) $oct3 = (0x10000 + $oct3) & 0xFFFF;
	echo " = ".decoct($oct3)." on $lnum\n";
    }
    
    // DEBUG: echo decoct($gAddr)."-".$type0."\t\t".decoct($oct1)."-".$type1."\t\t".decoct($oct2)."-".$type2."\t\t".decoct($oct3)."-".$type3."\n";
    // now we have addr and up to three octals
    $gAddr = PutBytes($gAddr, $oct1, $type1, $processor);
    $gAddr = PutBytes($gAddr, $oct2, $type2, $processor);
    $gAddr = PutBytes($gAddr, $oct3, $type3, $processor);
    return true;
}


function GetLineNumber ($s, &$lnum)
{
    $s1 = trim(substr($s, 0, 8));
    $lnum = intval($s1, 10);
    return substr($s, 8);
}


function GetOctal ( $s, &$num, &$type )
{
    $l = 0;
    $sbuf = "";
    $relative = false;
    while ($l<8 && strlen($s) > 0)
    {
        $fc = ord($s[0]);
        if ($fc == 0x09) { $l = (($l+8) >> 3) << 3; $s = substr($s, 1); break; }
        if ($fc == 0x20) { $l++; $s = substr($s, 1); continue; }
	// last character in word data can be ' - need to convert to relative addr
	if ($l == 6 && $fc == ord('\'')) {
	    $relative = true;
	} else {
	    // else check for non [0..7] (octal) characters
	    if ($fc < 0x30 || $fc > 0x37) { $type = -1; return ""; }
	}
        $sbuf .= chr($fc);
        $s = substr($s, 1);
        $l++;
    }
    // no data at all
    if (strlen($sbuf) == 0) {
        $type = 0;
        return $s;
    }
    // relative addr
    if ($relative) {
	$sbuf = substr($sbuf, 0, strlen($sbuf)-1);
    }
    // usual octal word of byte
    // 1 - byte, 2 - word, 3 - relative addr
    $type = 1;
    if (strlen($sbuf) > 3) $type = 2;
    if ($relative) {
        if ($type == 1) $type = -1; else $type = 3;
    }
    $num = octdec($sbuf);
    return $s;
}


function PutBytes ($adr, $w, $type, $proc)
{
    global $allRAM, $lnum;
    global $current_line;

    if ($adr > $allRAM[$proc.'max']) {
        echo "ERROR: address $adr is out of range on line $lnum\n";
	echo "$current_line\n";
        exit(1);
    }
    // type == 0 - don't use this
    if ($type == 0) return $adr;
    // type == 1 - its a byte
    if ($type == 1) { 
        $allRAM[$proc][$adr] = $w & 0xFF;
        return $adr+1; // return next addr
    }
    // type == 2|3 - its a word
    if ($type == 2 || $type == 3) {
        $allRAM[$proc][$adr] = $w & 0xFF;
        $allRAM[$proc][$adr+1] = ($w>>8) & 0xFF;
        return $adr+2;
    }
    echo "ERROR in PutBytes() $adr $w $type on line $lnum\n";
    echo "$current_line\n";
    exit(1);
}


// not including $end address
function WriteSection ($g, $proc, $start, $end)
{
    global $allRAM;
    $length = $end - $start;
    $count = 0;
    for ($i=$start; $i<($start+$length); $i++)
    {
        $byte = 0x00;
        if (isset($allRAM[$proc][$i])) $byte = $allRAM[$proc][$i];
        $s = chr($byte);
        fwrite($g, $s, 1);
        $count++;
    }
}


function WriteWord ($g, $w)
{
    $w = $w & 0xFFFF;
    $b1 = $w & 0xFF;
    $b2 = ($w & 0xFF00) >> 8;
    fwrite($g, chr($b1));
    fwrite($g, chr($b2));
}

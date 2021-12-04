<?php

$dataOutputCur = 0;
$dataOutputMax = 0;
$dataOutputPre = '';

function InitDataOutput ($pre, $max)
{
    global $dataOutputCur, $dataOutputMax, $dataOutputPre;
    $dataOutputCur = 0;
    $dataOutputMax = $max-1;
    $dataOutputPre = $pre;
}

function DataOutput ($f, $s)
{
    global $dataOutputCur, $dataOutputMax, $dataOutputPre;
    if ($dataOutputCur == 0) fputs($f, "\t".$dataOutputPre."\t"); else fputs($f, ", ");
    fputs($f, $s);
    if ($dataOutputCur == $dataOutputMax) fputs($f, "\n");
    $dataOutputCur++; if ($dataOutputCur > $dataOutputMax) $dataOutputCur = 0;
}

function FinishDataOutput($f)
{
    global $dataOutputCur;
    if ($dataOutputCur != 0) fputs($f, "\n");
}


// MAIN ////////////////////////////////////////////////////////////////////////

    // first character:
    // T - tiles 16x16 matrix - without mask => BOTH
    // S - sprites 16x16 matrix - with mask on transparency => BOTH
    // B - tiles 8x8 matrix - with mask on transparency => PPU
    // F - font 8x8 matrix - without mask => PPU
    // 
    //       mask is:
    //         plane-0: (byte) mask, data, mask, data ...
    //         planes-1,2: (byte) mask, mask, mask... (word) data, data, data...

    $tiles_arr = Array();
    $tiles8_arr = Array();
    $sprites_arr = Array();
    $font_arr = Array();
    $tiles_count = 0;
    $tiles8_count = 0;
    $sprites_count = 0;
    $font_count = 0;
    
    $cpu_bytes = 0;
    $ppu_bytes = 0;

    $spr_cnt_w = 0;
    $spr_cnt_h = 0;
    
    ProcessDir("./graphics/");

    $fout_cpu = fopen("./graphics/inc_cpu_sprites.mac", "w");
    $fout_ppu = fopen("./graphics/inc_ppu_sprites.mac", "w");

    OutPlane0Bytes($fout_ppu, "Tiles8Addr", $tiles8_arr, true);
    OutPlane0Bytes($fout_ppu, "FontAddr", $font_arr, false/*mask*/);
    OutPlane0Bytes($fout_ppu, "TilesAddr", $tiles_arr, false);

    OutPlane0Words($fout_ppu, "Sprites", $sprites_arr);
    OutPlane12DW($fout_cpu, "Tiles", $tiles_arr, false);
    OutPlane12DW($fout_cpu, "Sprites", $sprites_arr, true);

    fputs($fout_cpu, "\n");
    fputs($fout_ppu, "\n");
    fclose($fout_cpu);
    fclose($fout_ppu);

    echo "Tiles: $tiles_count\n";
    echo "Tile8: $tiles8_count\n";
    echo "Sprts: $sprites_count\n";
    echo " Font: $font_count\n";
    echo "  CPU: $cpu_bytes bytes\n";
    echo "  PPU: $ppu_bytes bytes\n";
    
    exit(0);
    
////////////////////////////////////////////////////////////////////////////////


function FindFiles ($location='', $fileregex='') 
{
    if (!$location or !is_dir($location) or !$fileregex) {
        return false;
    }
    $matchedfiles = array();
    $all = opendir($location);
    while ($file = readdir($all)) {
	if (!is_dir($location.'/'.$file)) {
            if (preg_match($fileregex,$file)) {
                array_push($matchedfiles,$location.'/'.$file);
            }
        }
    }
    closedir($all);
    unset($all);
    return $matchedfiles;
} 


function ProcessDir ( $dir )
{
    $files = FindFiles ($dir, '/^([sS]|[tT]|[bB]|[fF]).*\.([pP][nN][gG])$/');
    $count = count($files);
    echo "Dir: $dir, Files: $count\n";
    sort($files);
    for ($i=0; $i < $count; $i++) 
    {
        ProcessFile($files[$i]);
    }
}


function ProcessFile ( $fname )
{
    global $spr_cnt_w, $spr_cnt_h;
    
    $fnamebase = basename($fname);    
    $arr = explode(".", $fnamebase);
    $sname = $arr[0];
    $sprtype = strtoupper(substr($sname, 0, 1));
    if ($sprtype !== 'T' && $sprtype !== 'S' && $sprtype !== 'B' && $sprtype !== 'F') return;
    $sname = substr($sname, 1);
    echo "$fname - $sname\n";
    
    $img = imagecreatefrompng($fname);
    $width  = imagesx($img);
    $height = imagesy($img);
    if ($sprtype == 'T' || $sprtype == 'S')
    {
	$spr_cnt_w = intval($width/17);
	$spr_cnt_h = intval($height/17);
    }
    if ($sprtype == 'B' || $sprtype == 'F')
    {
	$spr_cnt_w = intval($width/9);
	$spr_cnt_h = intval($height/9);
    }
    $spr_cnt = $spr_cnt_w * $spr_cnt_h;

    for ($sprnum=0; $sprnum<$spr_cnt; $sprnum++) GetSprData($img, $sprnum, $sprtype);
}


function GetSprData ( $img, $sprnum, $type )
{
    global $spr_cnt_w, $sprites_count, $tiles_count, $tiles8_count, $font_count;
    global $sprites_arr, $tiles_arr, $tiles8_arr, $font_arr;
    
    $s_width = 16;
    $s_height = 16;
    if ($type == 'B' || $type == 'F') { $s_width=8; $s_height=8; }
    $xstart = intval($sprnum % $spr_cnt_w) * ($s_width+1);
    $ystart = intval($sprnum / $spr_cnt_w) * ($s_height+1);
    $xstart++; // +1pix for border
    $ystart++;
    $cur_dword = 0;
    $bn_max = 2; if ($type == 'B' || $type == 'F') $bn_max = 1;
    for ($y=$ystart; $y<($ystart+$s_height); $y++)
    {
        for ($bn=0; $bn<$bn_max; $bn++)
        {
            $x = $xstart + $bn*8;
            $res = 0;
            for ($i=0; $i<8; $i++, $x++)
            {
	        $res = ($res >> 1) & 0x7FFFFFFF;
                $rgb_index = imagecolorat($img, $x, $y);
                $rgba = imagecolorsforindex($img, $rgb_index);
                $r = $rgba['red'];
                $g = $rgba['green'];
                $b = $rgba['blue'];
                $a = $rgba['alpha'];
                if ($a < 100) { $res = $res | 0x80000000; }
		// color values for tiles or not transparent pix
		if ($type == 'T' || $a<100) 
		{
		    if ($r > 127) { $res = $res | 0x00800000; }
                    if ($g > 127) { $res = $res | 0x00008000; }
                    if ($b > 127) { $res = $res | 0x00000080; }
		}
            }
	    if ($type == 'S')
		$sprites_arr[$sprites_count][$cur_dword++] = $res;
	    if ($type == 'T')
		$tiles_arr[$tiles_count][$cur_dword++] = $res;
	    if ($type == 'B')
		$tiles8_arr[$tiles8_count][$cur_dword++] = $res;
	    if ($type == 'F')
		$font_arr[$font_count][$cur_dword++] = $res;
        }
    }
    if ($type == 'S') $sprites_count++;
    if ($type == 'T') $tiles_count++;
    if ($type == 'B') $tiles8_count++;
    if ($type == 'F') $font_count++;
}


// 8-pixel width 
function OutPlane0Bytes ($fout, $sname, $arr, $with_mask)
{
    global $ppu_bytes;
    
    fputs($fout, "\n$sname:\n");
    for ($number=0; $number<count($arr); $number++)
    {
	fputs($fout, '; '.str_pad($number,3,'0',STR_PAD_LEFT)."\n");
        $cn = 0;
        $cmax = 7;
	for ($idx=0; $idx<count($arr[$number]); $idx++)
	{
            if ($cn == 0) fputs($fout, "\t.BYTE\t"); else fputs($fout, ", ");
            $b = $arr[$number][$idx] & 0xFF;
	    if ($with_mask)
	    {
                // mask is by alpha-channel
	        $bmask = ($arr[$number][$idx] >> 24) & 0xFF;
                fputs($fout, decoct($bmask).","); $ppu_bytes++;
            }
            fputs($fout, decoct($b)); $ppu_bytes++;
            if ($cn == $cmax) fputs($fout, "\n");
            $cn++; if ($cn > $cmax) $cn = 0;
        }
        if ($cn != 0) fputs($fout, "\n");
    }
}


// 16-pixel width PPU output (with mask)
// variable height 16xDY
//
function OutPlane0Words ($fout, $sname, $arr)
{
    global $ppu_bytes;

    // get sprites idx-start, offset-start and idx-end
    $lstart = Array();
    $lend   = Array();
    $sdata  = Array();
    for ($number=0; $number<count($arr); $number++)
    {
	for ($idx=0, $line=0; $idx<count($arr[$number]); $idx+=2, $line++)
	{
            $m1 = ($arr[$number][$idx] >> 24) & 0xFF;
            $m2 = ($arr[$number][$idx+1] >> 24) & 0xFF;
            $wmask = ($m2<<8 | $m1) & 0xFFFF;
            $byte1 = $arr[$number][$idx] & 0xFF;
	    $byte2 = $arr[$number][$idx+1] & 0xFF;
	    $wdata1 = ($byte2 << 8) | $byte1;
	    $sdata[$number][$line][0] = $wmask;
	    $sdata[$number][$line][1] = $wdata1;
	    $was_data = true; if ($wmask == 0 && $wdata1 == 0) $was_data = false;
	    if ($was_data) {
		if (!isset($lstart[$number])) $lstart[$number] = $line;
		$lend[$number] = $line; // same line
	    }
	}
	if (!isset($lstart[$number])) {
	    $lstart[$number] = 0;
	    $lend[$number] = 0;
	}
    }

    // output sprites data
    fputs($fout, "\n$sname"."Data:\n");
    for ($number=0; $number<count($arr); $number++)
    {
	fputs($fout, '; '.str_pad($number,3,'0',STR_PAD_LEFT)."\n");
	InitDataOutput('.word', 8);
        for ($line=$lstart[$number]; $line<=$lend[$number]; $line++)
        {
	    DataOutput($fout, decoct($sdata[$number][$line][0])); $ppu_bytes += 2;
	    DataOutput($fout, decoct($sdata[$number][$line][1])); $ppu_bytes += 2;
        }
	FinishDataOutput($fout);
    }

    // PPU don't need sizes, its controlled from CPU
    // so - just offsets
    fputs($fout, "\n$sname"."Addr:\n");
    InitDataOutput('.word', 10);
    $offset = 0;
    for ($number=0, $offset=0; $number<count($arr); $number++) { 
        $dy = ($lend[$number]-$lstart[$number]) & 0xF; // it's DY-1
        DataOutput($fout, $sname."Data+".decoct($offset)); $ppu_bytes += 2;
        $offset += (($dy+1) *4); // 4-bytes for line
    }
    FinishDataOutput($fout);
}


// 16-pixel width CPU output (dwords)
// sprites with mask are variable height 16xDY
//
function OutPlane12DW ($fout, $sname, $arr, $with_mask)
{
    global $cpu_bytes;

    // get sprites idx-start, offset-start and idx-end
    $lstart = Array();
    $lend   = Array();
    $sdata  = Array();
    $maxspr = count($arr)<128 ? count($arr) : 128;
    for ($number=0; $number<$maxspr; $number++)
    {
	for ($idx=0, $line=0; $idx<count($arr[$number]); $idx+=2, $line++)
	{
            $m1 = ($arr[$number][$idx] >> 24) & 0xFF;
            $m2 = ($arr[$number][$idx+1] >> 24) & 0xFF;
            $wmask = ($m2<<8 | $m1) & 0xFFFF;
	    $w1 = ($arr[$number][$idx+0] >> 8) & 0xFFFF;
	    $w2 = ($arr[$number][$idx+1] >> 8) & 0xFFFF;
	    $wdata1 = ($w1 & 0x00FF) | (($w2 & 0x00FF) << 8);
	    $wdata2 = (($w1 & 0xFF00) >> 8) | ($w2 & 0xFF00);
	    $sdata[$number][$line][0] = $wmask;
	    $sdata[$number][$line][1] = $wdata1;
	    $sdata[$number][$line][2] = $wdata2;
	    $was_data = true; if ($wmask == 0 && $wdata1 == 0 && $wdata2 == 0) $was_data = false;
	    if ($was_data || !$with_mask) {
		if (!isset($lstart[$number])) $lstart[$number] = $line;
		$lend[$number] = $line; // same line
	    }
	}
	if (!$with_mask) {
	    $lstart[$number] = 0;
	    $lend[$number] = 15;
	}
	if (!isset($lstart[$number])) {
	    $lstart[$number] = 0;
	    $lend[$number] = 0;
	}
    }

    // output sprites data
    fputs($fout, "\n$sname"."Data:\n");
    for ($number=0; $number<$maxspr; $number++)
    {
	fputs($fout, '; '.str_pad($number,3,'0',STR_PAD_LEFT)."\n");
	InitDataOutput('.word', 8);
        for ($line=$lstart[$number]; $line<=$lend[$number]; $line++)
        {
	    if ($with_mask) { DataOutput($fout, decoct($sdata[$number][$line][0])); $cpu_bytes += 2; }
	    DataOutput($fout, decoct($sdata[$number][$line][1])); $cpu_bytes += 2;
	    DataOutput($fout, decoct($sdata[$number][$line][2])); $cpu_bytes += 2;
        }
	FinishDataOutput($fout);
    }

    // output sprites offsets and sizes
    if ($with_mask)
    {
        fputs($fout, "\n$sname"."Addr:\n");
        InitDataOutput('.word', 10);
	$offset = 0;
        for ($number=0, $offset=0; $number<$maxspr; $number++) { 
            $dy = ($lend[$number]-$lstart[$number]) & 0xF; // it's DY-1
            DataOutput($fout, $sname."Data+".decoct($offset)); $cpu_bytes += 2;
            $offset += (($dy+1) * 6); // 6-bytes for line
	}
        FinishDataOutput($fout);
        fputs($fout, "\n$sname"."Size:\n");
        InitDataOutput('.byte', 10);
        for ($number=0, $offset=0; $number<$maxspr; $number++) {
	    $ystart = $lstart[$number] & 0xF;
            $dy = ($lend[$number]-$lstart[$number]) & 0xF;
	    DataOutput($fout, decoct($ystart << 4 | $dy)); $cpu_bytes++;
	}
        FinishDataOutput($fout);
        fputs($fout, "\t.even\n");
    }
}

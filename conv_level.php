<?php
	echo "Processing levels ...\n";
	
	$CGL_LEV_DX = 4;
	$CGL_LEV_DY = 16;

	$gDX = $CGL_LEV_DX*15+1;
	$gDY = $CGL_LEV_DY*13+1;
	$gTotal = $gDX * $gDY;

	$arrReplaceFrom = Array();
	$arrReplaceTo = Array();

	// reading constants (not really correct but ok)
	$f = fopen("inc_common.mac", "r");
	while (!feof($f))
	{
		$line = trim(fgets($f));
		if (strlen($line) < 3) continue;
		if (!preg_match("/^(.*?)=(.*)$/", $line, $arr)) continue;
		if (($i = strpos($arr[2],';')) > 0) $arr[2] = substr($arr[2], 0, $i);
		$l = strlen($arr[2]);
		$num = 0; $base = 8; if ($arr[2][$l-1] == '.') $base = 10;
		$num = intval($arr[2], $base);
		array_push($arrReplaceFrom, strtolower(trim($arr[1])) );
		array_push($arrReplaceTo, $num);
	}
	fclose($f);

	// parsing inc_levels.mac

	$GlobalLevel = Array();
	$GlobalObj = Array();
	$idx = 0;

	$base = 10;
	$f = fopen ("inc_levels.mac", "r");
	while (!feof($f))
	{
		$line = trim(strtolower(fgets($f)));
		if (strlen($line) < 5) continue;
		if (preg_match("/\\.radix\s+8/", $line)) { $base = 8; continue; }
		if (preg_match("/\\.radix\s+10/", $line)) { $base = 10; continue; }
		if (preg_match("/\\.even/", $line)) { continue; }
		if (preg_match("/^\s*\\.byte\s+(.+)$/", $line, $arr)) $line = $arr[1];
		if (preg_match("/^\s*\\.word\s+(.+)$/", $line, $arr)) $line = $arr[1];
		if (($i=strpos($line, ':'))>0) {
			$label = strtolower(trim(substr($line, 0, $i)));
			$line = trim(substr($line, $i+1));
			$idx = 0;
		}
		if (strlen($line) == 0) continue;
		$arr = explode(',', $line);
		for ($i=0; $i<count($arr); $i++)
		{
			$s = trim($arr[$i]);
			$s = trim(str_replace($arrReplaceFrom, $arrReplaceTo, $s));
			if (strlen($s) == 0) {
				echo "$line\n";
			}
			$v = 0; 
			if (is_numeric($s)) {
				$v = intval($s, $base);
			} else {
				ob_start();
				$result = eval('$v = '.$s.';');
				$err = ob_get_contents();
				ob_end_flush();
				if (strlen($err) > 0) echo "ERROR in EVAL: $line\n";
			}
			if ($v > 255) {
				echo "ERROR: value > 255\n$line";
				exit(1);
			}
			if ($label == 'globallevel') array_push($GlobalLevel, $v);
			else if ($label == 'globalobj') array_push($GlobalObj, $v);
		}
	}
	fclose($f);

	echo "GlobalLevel: ", count($GlobalLevel), "\n";
	echo "GlobalObj: ", count($GlobalObj), "\n";

	if (count($GlobalLevel) != $gTotal) {
		echo "ERROR: GlobalLevel count not equals $gTotal";
		exit(1);
	}


function PackData ( $arrToPack, $name, $g )
{
	// create byte file
	$f = fopen("_levels.tmp", "wb");
	foreach ($arrToPack as $k => $v) fwrite($f, chr($v));
	fclose($f);

	// pack it
	exec("..\packers\lzsa3.exe _levels.tmp _levels.tmz");

	// write to PPU include macro-11 file
	$arrPpu = Array();
	$fname = "_levels.tmz";
	$f = fopen($fname, "rb");
	for ($i=0; $i<filesize($fname); $i++) {
		if (($v = fread($f, 1)) === false) {
			echo "ERROR reading $fname\n";
			exit(1);
		}
		array_push($arrPpu, ord($v));
	}
	fclose($f);

	fputs($g, "$name:\n");
	$len = 0; $sout = '';
	foreach ($arrPpu as $k => $v)
	{
		if ($len == 0) $sout .= "\t.byte\t";
		$sout .= decoct($v);
		if ($len < 15) { $sout .= ','; $len++; } else { $sout .= "\n"; $len = 0; }
	}
	if ($sout[strlen($sout)-1] == ',') $sout = substr($sout, 0, strlen($sout)-1);
	fputs($g, $sout . "\n");
	echo "Compressed $name: ".count($arrPpu)." bytes\n";
	unlink("_levels.tmp");
	unlink("_levels.tmz");
}

	$f = fopen("inc_levels_lz.mac", "w");
	PackData($GlobalLevel, "GlobalLevel", $f);
	PackData($GlobalObj, "GlobalObj", $f);
	fclose($f);

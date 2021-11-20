<?php

// TODO: do something with that shit ^_^

$now_counting = 1;
$arr = Array();
$idx = 0;
$cnt = 0;

$f = fopen('sound.txt', 'r');
while (!feof($f))
{
	$s = fgets($f);
	if ($now_counting == 1)
	{
		if ($s[0] == '-') {
			$arr[$idx] = $cnt;
			$cnt = 0;
			$idx++;
			$now_counting = -1;
		}
	} else {
		if ($s[0] != '-') {
			$arr[$idx] = $cnt;
			$cnt = 0;
			$idx++;
			$now_counting = 1;
		}		
	}
	$cnt++;
}
fclose($f);

$fout = fopen('sound_out.txt', 'w');
$cn = 0;
$cmax = 15; 
foreach ($arr as $k => $v)
{
	if ($cn == 0) fputs($fout, "\t.WORD\t"); else fputs($fout, ", "); 
	fputs($fout, "".$v);
	if ($cn == $cmax) fputs($fout, "\n");
	$cn++; if ($cn > $cmax) $cn = 0; 
}
fclose($fout);

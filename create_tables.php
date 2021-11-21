<?php
	// sprite shifting tables
	// (not needed, used ashc)
	for ($shift_pos = 2; $shift_pos <= 6; $shift_pos+=2)
	for ($byte=0; $byte<=255; $byte++)
	{
		$byte_out_1 = ($byte << $shift_pos) & 0xFF;
		$byte_out_2 = ($byte << $shift_pos) >>  8;
		$arr1[$shift_pos][$byte] = $byte_out_1;
		$arr2[$shift_pos][$byte] = $byte_out_2;
	}
	
	$f = fopen("tables.txt", "w");
	fputs($f, "ShiftTable1:\n");
	$col = 0;
	for ($shift_pos = 2; $shift_pos <= 6; $shift_pos+=2)
	for ($byte=0; $byte<=255; $byte++)
	{
		if ($col == 0) fputs($f, "\t.BYTE\t");
		fputs($f, decoct($arr1[$shift_pos][$byte]));
		if ($col != 15  && ($byte<255 || $shift_pos<6)) fputs($f, ",");
			else { fputs($f, "\n"); $col = -1; }
		$col++;
	}
	
	fputs($f, "\nShiftTable2:\n");
	$col = 0;
	for ($shift_pos = 2; $shift_pos <= 6; $shift_pos+=2)
	for ($byte=0; $byte<=255; $byte++)
	{
		if ($col == 0) fputs($f, "\t.BYTE\t");
		fputs($f, decoct($arr2[$shift_pos][$byte]));
		if ($col != 15  && ($byte<255 || $shift_pos<6)) fputs($f, ",");
			else { fputs($f, "\n"); $col = -1; }	
		$col++;
	}

	// part of videolines table in PPU
	//
	fputs($f, "\nVLinesTable:\n");
	$vaddr = 0100000;
	$paddr_start = 01130;
	$paddr = $paddr_start + 4;
	$col = 0;
	$count = 299;
	for ($i=0; $i<$count; $i++)	// (!) 296 lines
	{
		if ($col == 0) fputs($f, "\t.word\t");
		fputs($f, decoct($vaddr) . "," . decoct($paddr));
		$vaddr += 40;
		$paddr += 4;
		if ($col != 15  && $i < ($count-1)) { fputs($f, ", "); $col++; } else { fputs($f, "\n"); $col = 0; }
	}
	fputs($f, "\t.word\t" . decoct($vaddr) . "," . decoct($paddr_start) . "\n");

	fclose($f);

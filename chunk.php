<?php

function chunk_read($data, $X, $Z, $onlyBlocks = false){
	$totalOffset = 0;
	$size = strlen($data);
	$chunkBlocks = 16 * 16 * 128; //32768
	$chunk = array(
		"block" => array(),
		"meta" => array(),
		"blight" => array(),
		"slight" => array(),
	);
	$byte = "";
	$half = false;
	foreach($chunk as $type => $arr){
		if($onlyBlocks == true and $type != "block"){
			break;
		}
		for($offset=0;$offset<$chunkBlocks;++$offset){
			if($type=="block"){
				/*$x = $X + ($offset >> 11);
				$y = $offset & 0x7F;
				$z = $Z + (($offset & 0x780) >> 7 );*/
				$chunk[$type][] = read_byte($data{$totalOffset+$offset},false);
			}else{
				/*$x = $X + (($offset/2) >> 11);
				$y = ($offset/2) & 0x7F;
				$z = $Z + ((($offset/2) & 0x780) >> 7 );*/
				$byte = $data{$totalOffset+($offset/2)};				
				$chunk[$type][] = read_byte($byte & 0x0F,false);
				$chunk[$type][] = read_byte(($byte >> 4) & 0x0F,false);
				++$offset;
			}
		}
		$totalOffset += $offset;
	}
	return $onlyBlocks == true ? $chunk["block"]:$chunk;
}

function chunk_add($data, $x, $z){
	global $chunks;
	$x /= 16;
	$z /= 16;
	$chunks[$x."|".$z] = gzinflate(substr($data,2));
	return true;
}

function chunk_clean($x, $z){
	global $chunks;
	$x /= 16;
	$z /= 16;
	if(!isset($chunks[$X."|".$Z])){
		return false;
	}
	$chunks[$x."|".$z] = substr($chunks[$x."|".$z],0,16 + 16 * 128);
	return true;
}

function chunk_load($x, $z){
	global $chunks, $tchunk;
	if(!isset($tchunk[$x."|".$z]) and isset($chunks[$x."|".$z])){
		$tchunk[$x."|".$z] = chunk_read($chunks[$x."|".$z],$x,$z,true);
		return true;
	}elseif(isset($tchunk[$x."|".$z])){
		return true;
	}else{
		return false;
	}
}

function chunk_get_radius($x,$y,$z,$r=4){
	$r = abs($r);
	return chunk_get_zone($x-$r,$y-$r,$z-$r,$x+$r,$y+$r,$z+$r);	
}

function chunk_get_zone($x1,$z1,$y1, $x2,$z2,$y2){
	if($x1>$x2 or $y1>$y2 or $z1>$z2){
		return false;
	}
	$blocks = array();
	for($x=$x1;$x<=$x2;++$x){
		for($y=$y1;$y<=$y2;++$y){
			for($z=$z1;$z<=$z2;++$z){
				$blocks[$x][$y][$z] = chunk_get_block($x,$y,$z);
			}
		}
	}
	return $blocks;
}

function chunk_get_block($x,$y,$z){
	global $chunks;
	$x = intval($x);
	$y = intval($y);
	$z = intval($z);
	$X = intval($x / 16);
	$Z = intval($z / 16);
	
	$x %= 16;
	$y %= 128;
	$z %= 16;
	
	if(!isset($chunks[$X."|".$Z])){
		return false;
	}
	$index = $y + ($z * 128) + ($x * 128 * 16);
	return read_byte($chunks[$X."|".$Z]{$index},false);
}

function chunk_edit_block($x,$y,$z,$value){
	global $chunks;
	$x = intval($x);
	$y = intval($y);
	$z = intval($z);
	$X = intval($x / 16);
	$Z = intval($z / 16);	
	$x %= 16;
	$y %= 128;
	$z %= 16;

	if(!isset($chunks[$X."|".$Z])){
		return false;
	}
	$index = $y + ($z * 128) + ($x * 128 * 16);
	$chunks[$X."|".$Z]{$index} = write_byte($value,false);
	return true;
}

?>
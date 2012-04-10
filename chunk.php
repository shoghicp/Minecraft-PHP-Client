<?php

function chunk_read($data, $X, $Z, $onlyBlocks = false, $biome = false){
	global $protocol;
	if($protocol <= 23){
		return old_chunk_read($data, $X, $Z, $onlyBlocks);
	}
	$chunk = array(
		"block" => array(),
		"meta" => array(),
		"blight" => array(),
		"slight" => array(),
	);	
	 //Loop over 16x16x16 chunks in the 16x256x16 column
	 $offset = 0;
	 $read = 4096;
	 for ($i=0;$i<16;$i++) {
   //If the bitmask indicates this chunk has been sent...
   if ($bitmask & 1 << $i) {
     //Read data...
     $cubic_chunk_data = substr($data, $offset, $read); //2048 for the other arrays, where you'll need to split the data
     $offset += $read;
     $read = 2048;
     $len = strlen($cubic_chunk_data);
     for($j=0; $j<$len; $j++) {
       //Retrieve x,y,z and data from each element in cubic_chunk_array
       
       //Byte arrays
       $x = $chunk_x*16 + $j & 0x0F;
       $y = $i*16 + $j >> 8;
       $z = $chunk_z*16 + ($j & 0xF0) >> 4;
       $data = $cubic_chunk_data[$j];
       
       //Nibble arrays
       $data1 = $cubic_chunk_data[$j] & 0x0F;
       $data2 = $cubic_chunk_data[$j] >> 4;
       
       $k = 2*$j;
       $x1 = $chunk_x*16 + $k & 0x0F;
       $y1 = $i*16       + $k >> 8;
       $z1 = $chunk_z*16 + ($k & 0xF0) >> 4;
       
       $k++;
       $x2 = $chunk_x*16 + $k & 0x0F;
       $y2 = $i*16       + $k >> 8;
       $z2 = $chunk_z*16 + ($k & 0xF0) >> 4;
      }
   }
 }
	
}

function old_chunk_read($data, $X, $Z, $onlyBlocks = false){
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
	global $chunks, $protocol;
	if($protocol <= 23){
		return old_chunk_get_block($x,$y,$z);
	}
	$x = intval($x);
	$y = intval($y);
	$z = intval($z);
	$X = intval($x / 16);
	$Z = intval($z / 16);
	
	$x %= 16;
	$y %= 128;
	$z %= 16;
	
	if(!isset($chunks[$X."|".$Z])){
		return 0x00; //AIR
	}
	$index = $y + ($z * 128) + ($x * 128 * 16);
	return read_byte($chunks[$X."|".$Z]{$index},false);
}

function old_chunk_get_block($x,$y,$z){
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
		return 0x00; //AIR
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

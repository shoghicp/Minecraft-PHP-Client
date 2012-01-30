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
				$x = $X + ($offset >> 11);
				$y = $offset & 0x7F;
				$z = $Z + (($offset & 0x780) >> 7 );
				$chunk[$type][$x."|".$y."|".$z] = read_byte($data{$totalOffset+$offset},false);
			}else{
				$x = $X + (($offset/2) >> 11);
				$y = ($offset/2) & 0x7F;
				$z = $Z + ((($offset/2) & 0x780) >> 7 );
				$byte = $data{$totalOffset+($offset/2)};
				$chunk[$type][$x."|".$y."|".$z] = read_byte(($byte >> 4) & 0x0F,false);
				$chunk[$type][$x."|".$y."|".$z] = read_byte($byte & 0x0F,false);
				++$offset;
			}
		}
		$totalOffset += $offset;
	}
	return $chunk;
}

?>
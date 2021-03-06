<?php

function console($message, $EOL = true, $log = true){
	global $path;
	$message .= $EOL == true ? PHP_EOL:"";
	if($log and arg("log", false) === true or (arg("log", false) != false and arg("log", false) != "packets")){
		file_put_contents($path."console.log", $message, FILE_APPEND);
	}
	echo $message;
}

function string_pack($len){
	return "\00".pack("H*",str_pad(dechex($len),2,"0",STR_PAD_LEFT));
}

function write_string($str){
	return write_short(strlen($str)).endian($str);
}

function read_byte($str, $signed = true){
	$b = hexdec(bin2hex($str{0}));
	if($signed == true){
		$b = $b>127 ? -(256-$b+1):$b;
	}
	return $b;
}
function write_byte($value, $signed = true){
	if($signed == true){
		$value = ($value <= -1) ? (256+$value):$value;
	}
	return pack("c", $value);
}

function read_short($str){
	list(,$unpacked) = unpack("n", substr($str, 0, 2));
	if($unpacked >= pow(2, 15)) $unpacked -= pow(2, 16); // Convert unsigned short to signed short.
	return $unpacked;
}
function write_short($value){
	if($value < 0){
		$value += pow(2, 16); 
	}
	return pack("n", $value);
}

function read_int($str){
	list(,$unpacked) = unpack("N", substr($str, 0, 4));
	if($unpacked >= pow(2, 31)) $unpacked -= pow(2, 32); // Convert unsigned int to signed int
	return $unpacked;
}
function write_int($value){
	if($value < 0){
		$value += pow(2, 32); 
	}
	return pack("N", $value);
}


function read_float($str){
	list(,$value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0")?unpack('f', substr($str,0, 4)):unpack('f', strrev(substr($str,0, 4)));
	return $value;
}
function write_float($value){
	return (pack('d', 1) == "\77\360\0\0\0\0\0\0")?pack('f', $value):strrev(pack('f', $value));
}

function read_double($str, $signed = true){
	list(,$value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0")?unpack('d', substr($str,0, 8)):unpack('d', strrev(substr($str,0, 8)));
	return $value;
}
function write_double($value){
	return (pack('d', 1) == "\77\360\0\0\0\0\0\0")?pack('d', $value):strrev(pack('d', $value));
}

function read_long($str, $signed = true){
	$n = "";
	for($i=0;$i<8;++$i){
		$n .= bin2hex($str{$i});
	}
	$n = hexdec($n);
	if($signed == true){
		$n = $n>9223372036854775807 ? -(18446744073709551614-$n+1):$n;
	}
	return sprintf("%.0F", $n);
}
function write_long($value){
	return (pack('d', 1) == "\77\360\0\0\0\0\0\0")?pack('d', $value):strrev(pack('d', $value));
}

function convert($format, $str){
	$ret = unpack($format, $str);
	if(is_array($ret)){
		$ret = implode($ret);
	}
	return $ret;
}

function arg($name, $default){
	global $arguments, $argv;
	if(!isset($arguments)){
		$arguments = arguments($argv);
	}
	
	if(isset($arguments["commands"][$name])){
		return $arguments["commands"][$name];
	}else{
		return $default;
	}
}

function arguments ( $args ){
	if(!is_array($args)){
		$args = array();
	}
    array_shift( $args );
    $args = join( $args, ' ' );

    preg_match_all('/ (--\w+ (?:[= ] [^-]+ [^\s-] )? ) | (-\w+) | (\w+) /x', $args, $match );
    $args = array_shift( $match );

    /*
        Array
        (
            [0] => asdf
            [1] => asdf
            [2] => --help
            [3] => --dest=/var/
            [4] => -asd
            [5] => -h
            [6] => --option mew arf moo
            [7] => -z
        )
    */

    $ret = array(
        'input'    => array(),
        'commands' => array(),
        'flags'    => array()
    );

    foreach ( $args as $arg ) {

        // Is it a command? (prefixed with --)
        if ( substr( $arg, 0, 2 ) === '--' ) {

            $value = preg_split( '/[= ]/', $arg, 2 );
            $com   = substr( array_shift($value), 2 );
            $value = join($value);

            $ret['commands'][$com] = !empty($value) ? $value : true;
            continue;

        }

        // Is it a flag? (prefixed with -)
        if ( substr( $arg, 0, 1 ) === '-' ) {
            $ret['flags'][] = substr( $arg, 1 );
            continue;
        }

        $ret['input'][] = $arg;
        continue;

    }

    return $ret;
}

function min_struct($array){
	$offset = 0;
	foreach($array as $type){
		switch($type){
			case "float":
			case "int":
				$offset += 4;
				break;
			case "double":
			case "long":
				$offset += 8;
				break;
			case "bool":
			case "boolean":
			case "byte":
				$offset += 1;
				$offset += 1;
				break;
			case "short":
				$offset += 2;
				break;	
		}
	}
	return $offset;
}

function wait_buffer($to){
	global $buffer, $connected;
	while(!isset($buffer{$to}) and $connected){
		buffer(); //FIXES SLOW-CONNECTION ERROR
	}
}

function parse_packet(){
	global $buffer, $pstruct, $path, $connected;
	$pid = bin2hex($buffer{0});
	$data = array();	
	$pdata = array();
	$raw = array();
	$offset = 1;
	
	if(!isset($pstruct[$pid])){
		write_packet("ff", array("message" => "Bad packet id ".$pid));
		if(arg("log", false) === true or (arg("log", false) != false and arg("log", false) == "packets")){
			$p = "==".time()."==> ERROR Bad packet id $pid :".PHP_EOL;
			$p .= hexdump(substr($buffer,0,512), false, false, true);
			$p .= PHP_EOL . "--------------- (512 byte extract) ----------" .PHP_EOL .PHP_EOL;
			file_put_contents($path."packets.log", $p, FILE_APPEND);
		}
		return array("pid" => "ff", "message" => "Bad packet id ".$pid);
	}
	wait_buffer(min_struct($pstruct[$pid]));
	$field = 0;
	$continue = true;
	foreach($pstruct[$pid] as $type){
		if($continue == false){
			break;
		}
		switch($type){
			case "int":
				$raw[] = $r = substr($buffer,$offset, 4);
				$pdata[] = read_int($r);
				if($field == 5 and $pid == "17" and $pdata[count($pdata)-1] == 0){
					$continue = false;
				}
				$offset += 4;
				break;
			case "string":
				$len = read_short(substr($buffer,$offset,2));
				$offset += 2;
				wait_buffer($offset+$len*2-1);
				$raw[] = $r = substr($buffer,$offset,$len * 2);
				$pdata[] = utf16_decode($r);
				$offset += $len * 2;
				break;
			case "long":
				$raw[] = $r = substr($buffer,$offset, 8);
				$pdata[] = read_long($r);
				$offset += 8;
				break;
			case "byte":
				$raw[] = $r = $buffer{$offset};
				$pdata[] = read_byte($r);
				$offset += 1;
				break;				
			case "ubyte":
				$raw[] = $r = $buffer{$offset};
				$pdata[] = read_byte($r, false);
				$offset += 1;
				break;
			case "float":
				$raw[] = $r = substr($buffer,$offset, 4);
				$pdata[] = convert("f", $r);
				$offset += 4;
				break;
			case "double":
				$raw[] = $r = substr($buffer,$offset, 8);
				$pdata[] = read_double($r);
				$offset += 8;
				break;
			case "short":
				$raw[] = $r = substr($buffer,$offset,2);
				$pdata[] = read_short($r);
				$offset += 2;
				break;
			case "bool":
			case "boolean":
				$raw[] = $r = $buffer{$offset};
				$pdata[] = read_byte($r, false) == 0 ? false:true;
				$offset += 1;
				break;
			case "explosionRecord":
				$r = array();
				for($i=$pdata[4];$i>0;--$i){
					$r[] = array(read_byte($buffer{$offset}),read_byte($buffer{$offset+1}),read_byte($buffer{$offset+2}));
					$offset += 3;
				}
				$pdata[] = $r;
				break;
			case "byteArray":
				$len = $pdata[count($pdata)-1];
				$pdata[] = substr($buffer,$offset,$len);
				$offset += $len;
				break;
			case "chunkArray":
				$len = max(0,$pdata[6]);
				$first = false;
				$r = "";
				while(strlen($r)<$len){ //Sometimes low-bandwidth servers made client a crash
					if($first == true){
						buffer();
						global $buffer;
					}
					$first = true;						
					$r = substr($buffer, $offset, $len);
				}
				$pdata[] = $r;
				$offset += $len;
				break;
			case "multiblockArray":
				$count = $pdata[count($pdata)-1];
				for($i=0;$i<$count;++$i){
					//read_short(substr($buffer,$offset,2));
					$offset += 2;
				}
				for($i=0;$i<$count;++$i){
					//read_byte($buffer{$offset});
					$offset += 1;
				}
				for($i=0;$i<$count;++$i){
					//read_byte($buffer{$offset});
					$offset += 1;
				}
				$pdata[] = "";
				break;
			case "newChunkArray":
				$len = max(0,$pdata[5]);
				$first = false;
				$r = "";
				while(strlen($r)<$len){ //Sometimes low-bandwidth servers made client a crash
					if($first == true){
						buffer();
						global $buffer;
					}
					$first = true;						
					$r = substr($buffer, $offset, $len);
				}
				$pdata[] = $r;
				$offset += $len;
				break;
			case "newMultiblockArray":
				$count = $pdata[3];
				$pdata[] = substr($buffer, $offset, $count);
				$offset += $count;
				break;
			case "slotArray":
			case "slotData":
				$scount = $type == "slotData" ? 1:$pdata[count($pdata)-1];
				$d = array();
				for($i=0;$i<$scount;++$i){
					$id = read_short(substr($buffer,$offset,2));
					$offset += 2;
					if($id != -1){
						$count = read_byte($buffer{$offset});						
						$offset += 1;
						$meta = read_short(substr($buffer,$offset,2));
						$offset += 2;
						$d[$i] = array($id,$count,$meta);
						$enchantable_items = array(
							 0x103, #Flint and steel
							 0x105, #Bow
							 0x15A, #Fishing rod
							 0x167, #Shears
							 
							 #TOOLS
							 #sword, shovel, pickaxe, axe, hoe
							 0x10C, 0x10D, 0x10E, 0x10F, 0x122, #WOOD
							 0x110, 0x111, 0x112, 0x113, 0x123, #STONE
							 0x10B, 0x100, 0x101, 0x102, 0x124, #IRON
							 0x114, 0x115, 0x116, 0x117, 0x125, #DIAMOND
							 0x11B, 0x11C, 0x11D, 0x11E, 0x126, #GOLD
							 
							 #ARMOUR
							 #helmet, chestplate, leggings, boots
							 0x12A, 0x12B, 0x12C, 0x12D, #LEATHER
							 0x12E, 0x12F, 0x130, 0x131, #CHAIN
							 0x132, 0x133, 0x134, 0x135, #IRON
							 0x136, 0x137, 0x138, 0x139, #DIAMOND
							 0x13A, 0x13B, 0x13C, 0x13D, #GOLD
						);
						if(in_array($id, $enchantable_items)){
							$len = read_short(substr($buffer,$offset,2));
							$offset += 2;
							if($len > -1){
								$arr = substr($buffer, $offset, $len);
								$offset += $len;
							}
						}
					}
				}
				$pdata[] = $d;
				break;
			case "entityMetadata":
				$m = array();
				$b = read_byte($buffer{$offset}, false);
				while($b != 127){
					$offset += 1;
					$bottom = $b & 0x1F;
					$type = $b >> 5;
					switch($type){
						case 0:
							$r = read_byte($buffer{$offset});
							$offset += 1;
							break;
						case 1:
							$r = read_short(substr($buffer,$offset,2));
							$offset += 2;
							break;
						case 2:
							$r = read_int(substr($buffer,$offset, 4));
							$offset += 4;
							break;
						case 3:
							$r = convert("f", substr($buffer,$offset, 4));
							$offset += 4;
							break;
						case 4:
							$len = read_short(substr($buffer,$offset,2));
							$offset += 2;
							$r = no_endian(substr($buffer,$offset,$len * 2));
							$offset += $len * 2;
							break;
						case 5:
							$r = array("id" => read_short(substr($buffer,$offset,2)), "count" => read_byte($buffer{$offset+2}), "damage" => read_short($buffer{$offset+3}.$buffer{$offset+4}));
							$offset += 5;
							break;
							
						case 6:
							$r = array();
							for($i=0;$i<3;++$i){
								$r[] = convert("I", substr($buffer,$offset, 4));
								$offset += 4;
							}
							break;
							
					}
					$m[] = $r;
					$b = read_byte($buffer{$offset}, false);
				}
				$offset += 1;
				$pdata[] = $m;
				break;
				
				
		}
		++$field;
	}
	

	if(arg("log", false) === true or (arg("log", false) != false and arg("log", false) == "packets")){
		$p = "==".time()."==> RECEIVED Packet $pid, lenght $offset :".PHP_EOL;
		$p .= hexdump(substr($buffer,0,$offset), false, false, true);
		$p .= PHP_EOL .PHP_EOL;
		file_put_contents($path."packets.log", $p, FILE_APPEND);
	}elseif(arg("log", false) != false and arg("log", false) == "raw"){
		file_put_contents($path."raw_recv.log", substr($buffer,0,$offset), FILE_APPEND);
	}
	
	$buffer = substr($buffer, $offset); // Clear packet	
	
	switch($pid){
		case "00":
			$data["ka_id"] = $pdata[0];
			break;
		case "01": //Login
			$data["eid"] = $pdata[0];
			$data["seed"] = $pdata[2];
			if($protocol >= 23){
				$data["level_type"] = $pdata[3];
				$data["mode"] = $pdata[4];
				$data["dimension"] = $pdata[5];
				$data["difficulty"] = $pdata[6];
				$data["height"] = $pdata[7];
				$data["max_players"] = $pdata[8];
			}else{
				$data["mode"] = $pdata[3];
				$data["dimension"] = $pdata[4];
				$data["difficulty"] = $pdata[5];
				$data["height"] = $pdata[6];
				$data["max_players"] = $pdata[7];
			}
			break;
		case "02": //Handshake
			$data["server_id"] = $pdata[0];
			break;
		case "03": //Chat
			$data["message"] = $pdata[0];
			break;
		case "04": //Time update
			$data["time"] = $pdata[0];//unpack("L*",substr($buffer,0,8));
			break;
		case "06": //Spawn pos
			$data["x"] = $pdata[0];
			$data["y"] = $pdata[1];
			$data["z"] = $pdata[2];
			break;
		case "08":
			$data["health"] = $pdata[0];
			$data["food"] = $pdata[1];
			$data["saturation"] = $pdata[1];
			break;
		case "0d":
			$data["x"] = $pdata[0];
			$data["stance"] = $pdata[1];
			$data["y"] = $pdata[2];
			$data["z"] = $pdata[3];
			$data["yaw"] = $pdata[4];
			$data["pitch"] = $pdata[5];
			$data["ground"] = $pdata[6];
			break;
		case "14": //named entity spawn		
			$data["eid"] = $pdata[0];
			$data["name"] = $pdata[1];
			$data["type"] = 100;
			$data["x"] = $pdata[2] / 32;
			$data["y"] = $pdata[3] / 32;
			$data["z"] = $pdata[4] / 32;
			break;
		case "17":
			$data["eid"] = $pdata[0];
			$data["type"] = $pdata[1];
			$data["x"] = $pdata[2] / 32;
			$data["y"] = $pdata[3] / 32;
			$data["z"] = $pdata[4] / 32;					
			break;
		case "18": 
			$data["eid"] = $pdata[0];
			$data["type"] = $pdata[1];
			$data["x"] = $pdata[2] / 32;
			$data["y"] = $pdata[3] / 32;
			$data["z"] = $pdata[4] / 32;
			break;
		case "1d":
			$data["eid"] = $pdata[0];
			break;
		case "1f":
			$data["eid"] = $pdata[0];
			$data["dX"] = $pdata[1] / 32;
			$data["dY"] = $pdata[2] / 32;
			$data["dZ"] = $pdata[3] / 32;
			break;
		case "20":
			$data["eid"] = $pdata[0];
			$data["yaw"] = $pdata[1];
			$data["pitch"] = $pdata[2];
			break;
		case "21":
			$data["eid"] = $pdata[0];
			$data["dX"] = $pdata[1] / 32;
			$data["dY"] = $pdata[2] / 32;
			$data["dZ"] = $pdata[3] / 32;
			$data["yaw"] = $pdata[4];
			$data["pitch"] = $pdata[5];			
			break;
		case "22":
			$data["eid"] = $pdata[0];
			$data["x"] = $pdata[1] / 32;
			$data["y"] = $pdata[2] / 32;
			$data["z"] = $pdata[3] / 32;
			$data["yaw"] = $pdata[4];
			$data["pitch"] = $pdata[5];				
			break;
		case "33":
			if($protocol <= 23){
				$data["x"] = $pdata[0];
				$data["y"] = $pdata[1];
				$data["z"] = $pdata[2];
				$data["xS"] = $pdata[3];
				$data["yS"] = $pdata[4];
				$data["zS"] = $pdata[5];
				$data["lenght"] = $pdata[6];
				$data["chunk"] = $pdata[7];
			}else{
				$data["x"] = $pdata[0];
				$data["z"] = $pdata[1];
				$data["continuous"] = $pdata[2];
				$data["pbm"] = $pdata[3];
				$data["abm"] = $pdata[4];
				$data["lenght"] = $pdata[5];
				$data["chunk"] = $pdata[7];
			}			
			break;
		case "34":
			if($protocol <= 23){
				$data["x"] = $pdata[0];
				$data["z"] = $pdata[1];
				$data["size"] = $pdata[2];
				$data["carray"] = $pdata[3];
				$data["tarray"] = $pdata[4];
				$data["marray"] = $pdata[5];
			}else{
				$data["x"] = $pdata[0];
				$data["z"] = $pdata[1];
				$data["count"] = $pdata[2];
				$data["size"] = $pdata[3];
				/*$data["carray"] = $pdata[4];
				$data["tarray"] = $pdata[5];
				$data["marray"] = $pdata[6];*/
			}
			break;
		case "35":
			$data["x"] = $pdata[0];
			$data["y"] = $pdata[1];
			$data["z"] = $pdata[2];
			$data["type"] = $pdata[3];
			$data["meta"] = $pdata[4];
			break;
		case "46":
			$data["reason"] = $pdata[0];
			if($protocol <= 14){
				$data["mode"] = $pdata[1];
			}
			break;
		case "67":
			$data["wid"] = $pdata[0];
			$data["slot"] = $pdata[1];
			$data["sdata"] = $pdata[2];
			break;
		case "68":
			$data["wid"] = $pdata[0];
			$data["count"] = $pdata[1];
			$data["sdata"] = $pdata[2];
			break;
		case "82":
			$data["x"] = $pdata[0];
			$data["y"] = $pdata[1];
			$data["z"] = $pdata[2];
			$data["text"] = array($pdata[3], $pdata[4], $pdata[5], $pdata[6]);
			break;
		case "fa":
			$data["channel"] = $pdata[0];
			$data["lenght"] = $pdata[1];
			$data["data"] = $pdata[2];
			break;
		case "ff":
			$data["message"] = $pdata[0];
			break;
	}
	$data["pid"] = $pid;
	$data["raw"] = $raw;
	return $data;
}

function write_packet($pid,$data = array(), $raw = false){
	global $sock, $path, $protocol, $connected;
	if($raw == false){
		switch($pid){
			case "00":
				$packet = "\x00".
				($protocol <= 14 ? "":$data["raw"][0]);
				break;
			case "01":	//Login	
				$packet = "\x01".
				write_int($data["version"]).
				write_string($data["username"]);
				if($protocol<=23){
					$packet .= write_long(0);
				}
				if($protocol >= 23){
					$packet .= write_string("");
				}
				if($protocol > 14){
					$packet .= write_int(0);
					if($protocol >= 23){
						$packet .= write_int(0);
					}else{
						$packet .= write_byte(0);
					}
					$packet .= write_byte(0).
					write_byte(0);
				}
				$packet .= write_byte(0);
				break;
			
			case "02": //Handshake
				$packet = "\x02";
				if($protocol > 23 ){
					$packet .= write_string($data["username"].";".$data["server"]);
				}else{
					$packet .= write_string($data["username"]);
				}
				break;
			case "03":
				$packet = "\x03".
				write_string($data["message"]);
				break;
			case "07":
				$packet = "\x07".
				write_int($data["eid"]).
				write_int($data["target"]).
				($data["left"] == true ? "\x01":"\x00");
				break;
			case "09":
				$packet = "\x09".
				write_byte($data["dimension"]).
				write_byte($data["difficulty"]).
				write_byte($data["mode"]).
				write_short($data["height"]).
				write_long($data["seed"]).
				($protocol >= 23 ? write_string($data["level_type"]):"");
				break;
			case "0a":
				$packet = "\x0a".
				($data["ground"] == true ? "\x01":"\x00");
				break;	
			case "0b":
				$packet = "\x0b".
				write_double($data["x"]).
				write_double($data["y"]).
				write_double($data["stance"]).
				write_double($data["z"]).
				($data["ground"] == true ? "\x01":"\x00");
				break;
			case "0c":
				$packet = "\x0c".
				write_float($data["yaw"]).
				write_float($data["pitch"]).
				($data["ground"] == true ? "\x01":"\x00");
				break;
			case "0d":
				$packet = "\x0d".
				write_double($data["x"]).
				write_double($data["y"]).
				write_double($data["stance"]).
				write_double($data["z"]).
				write_float($data["yaw"]).
				write_float($data["pitch"]).
				($data["ground"] == true ? "\x01":"\x00");
				break;
			case "0e":
				$packet = "\x0e".
				write_byte($data["status"]).
				write_int($data["x"]).
				write_byte($data["y"]).
				write_int($data["z"]).
				write_byte($data["face"]);			
				break;
			case "0f":
				$packet = "\x0f".
				write_int($data["x"]).
				write_byte($data["y"]).
				write_int($data["z"]).
				write_byte($data["direction"]).
				
				write_short($data["slot"][0]);
				if($data["slot"][0]!=-1){
					$packet .= write_byte($data["slot"][1]).
					write_short($data["slot"][2]);
				}				
				break;
			case "10":
				$packet = "\x10".
				write_short($data["slot"]);
				break;
			case "12":
				$packet = "\x12".
				write_int($data["eid"]).
				write_byte($data["animation"]);
				break;
			case "13":
				$packet = "\x13".
				write_int($data["eid"]).
				write_byte($data["action"]);
				break;
			case "1b":
				/*$packet = "\x1b".
				write_float($data["x"]).
				write_float($data["y"]).
				write_float($data["stance"]).
				write_float($data["z"]).
				write_float($data["yaw"]).
				write_float($data["pitch"]).
				($data["ground"] == true ? "\x01":"\x00");	*/		
				break;
			case "27":
				$packet = "\x27".
				write_int($data["eid"]).
				write_int($data["vid"]);
				break;				
			case "fe":
				$packet = "\xfe";
				break;
			case "ff":
				$packet = "\xff".
				write_string($data["message"]);
				break;
		}
	}else{
		$packet = pack("H*" , $pid);
		foreach($data["raw"] as $field){
			$packet .= $field;
		}
	}
	if(!$connected){
		return false;
	}
	
	if(arg("log", false) === true and (arg("log", false) != false and arg("log", false) == "packets")){
		$len = strlen($packet);
		$p = "==".time()."==> SENT Packet $pid, lenght $len:".PHP_EOL;
		$p .= hexdump($packet, false, false, true);
		$p .= PHP_EOL .PHP_EOL;
		file_put_contents($path."packets.log", $p, FILE_APPEND);
	}elseif(arg("log", false) != false and arg("log", false) == "raw"){
		file_put_contents($path."raw_sent.log", $packet, FILE_APPEND);
	}
	
	return @socket_write($sock, $packet);
}

function endian($str){
	$new = '';
	$len = strlen($str);
	for($i=0;$i<$len;++$i){
		$new .= "\00".$str{$i};
	}
	return $new;
}
function no_endian($str){
	$len = strlen($str);
	$f="";
	for($i=0;$i<$len;++$i){
		if($i%2==1 and $i > 0){
			$f .= $str{$i};
		}
	}
	return $f;
}

function curl_get($page){
	$ch = curl_init ($page);
	curl_setopt ($ch, CURLOPT_HTTPHEADER, array('User-Agent: Minecraft PHP Client'));
	curl_setopt ($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	return curl_exec ($ch);
}
function curl_post($page, $args){
	$ch = curl_init($page);
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $args);
	curl_setopt ($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt ($ch, CURLOPT_HTTPHEADER, array('User-Agent: Minecraft PHP Client'));
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	return curl_exec($ch);
}

function buffer(){
	global $buffer, $sock, $connected;
	if(!isset($buffer)){
		$buffer = "";
	}
	$len = strlen($buffer);
	if(THREADED and !CHILD){
		if($len < (MAX_BUFFER_BYTES / 8)){
			$buffer .= fork_buffer();
		}
		return;
	}
	
	if($len < (MAX_BUFFER_BYTES / 8)){
		if(strlen($buffer) < 128 and $connected){
			socket_set_block($sock);
		}else{
			socket_set_nonblock($sock);
		}
		$read = @socket_read($sock,MAX_BUFFER_BYTES-$len, PHP_BINARY_READ);
		if($read != false and $read != ""){
			$buffer .= $read;
		}elseif(socket_last_error($sock) == 104){
			$connected = false;
		}
	}
	
}


/**
 * View any string as a hexdump.
 *
 * This is most commonly used to view binary data from streams
 * or sockets while debugging, but can be used to view any string
 * with non-viewable characters.
 *
 * @version     1.3.2
 * @author      Aidan Lister <aidan@php.net>
 * @author      Peter Waller <iridum@php.net>
 * @link        http://aidanlister.com/2004/04/viewing-binary-data-as-a-hexdump-in-php/
 * @param       string  $data        The string to be dumped
 * @param       bool    $htmloutput  Set to false for non-HTML output
 * @param       bool    $uppercase   Set to true for uppercase hex
 * @param       bool    $return      Set to true to return the dump
 */
function hexdump ($data, $htmloutput = true, $uppercase = false, $return = false)
{
    // Init
    $hexi   = '';
    $ascii  = '';
    $dump   = ($htmloutput === true) ? '<pre>' : '';
    $offset = 0;
    $len    = strlen($data);
 
    // Upper or lower case hexadecimal
    $x = ($uppercase === false) ? 'x' : 'X';
 
    // Iterate string
    for ($i = $j = 0; $i < $len; $i++)
    {
        // Convert to hexidecimal
        $hexi .= sprintf("%02$x ", ord($data[$i]));
 
        // Replace non-viewable bytes with '.'
        if (ord($data[$i]) >= 32) {
            $ascii .= ($htmloutput === true) ?
                            htmlentities($data[$i]) :
                            $data[$i];
        } else {
            $ascii .= '.';
        }
 
        // Add extra column spacing
        if ($j === 7) {
            $hexi  .= ' ';
            $ascii .= ' ';
        }
 
        // Add row
        if (++$j === 16 || $i === $len - 1) {
            // Join the hexi / ascii output
            $dump .= sprintf("%04$x  %-49s  %s", $offset, $hexi, $ascii);
 
            // Reset vars
            $hexi   = $ascii = '';
            $offset += 16;
            $j      = 0;
 
            // Add newline
            if ($i !== $len - 1) {
                $dump .= "\n";
            }
        }
    }
 
    // Finish dump
    $dump .= $htmloutput === true ?
                '</pre>' :
                '';
    $dump .= "\n";
	
	$dump = preg_replace("/[^[:print:]\\r\\n]/", ".", $dump);
 
    // Output method
    if ($return === false) {
        echo $dump;
    } else {
        return $dump;
    }
}

function utf16_decode( $str ) {
    if( strlen($str) < 2 ) return $str;
    $bom_be = true;
    $c0 = ord($str{0});
    $c1 = ord($str{1});
    if( $c0 == 0xfe && $c1 == 0xff ) { $str = substr($str,2); }
    elseif( $c0 == 0xff && $c1 == 0xfe ) { $str = substr($str,2); $bom_be = false; }
    $len = strlen($str);
    $newstr = '';
    for($i=0;$i<$len;$i+=2) {
        if( $bom_be ) { $val = ord($str{$i})   << 4; $val += ord($str{$i+1}); }
        else {        $val = ord($str{$i+1}) << 4; $val += ord($str{$i}); }
        $newstr .= ($val == 0x228) ? "\n" : chr($val);
    }
    return $newstr;
}
?>

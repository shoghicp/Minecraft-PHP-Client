<?php



function string_pack($len){
	return "\00".pack("H*",str_pad(dechex($len),2,"0",STR_PAD_LEFT));
}

function read_byte($str, $signed = true){
	$b = hexdec(bin2hex($str));
	if($signed == true){
		$b = $b>127 ? -(256-$b+1):$b;
	}
	return $b;
}

function read_short($str, $signed = true){
	list(,$unpacked) = unpack("n", substr($str, 0, 2));
	if($unpacked >= pow(2, 15)) $unpacked -= pow(2, 16); // Convert unsigned short to signed short.
	return $unpacked;
}

function read_int($str, $signed = true){
	list(,$unpacked) = unpack("N", substr($str, 0, 4));
	if($unpacked >= pow(2, 31)) $unpacked -= pow(2, 32); // Convert unsigned int to signed int
	return $unpacked;
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

function parse_packet(){
	global $buffer, $pstruct, $path;
	$pid = bin2hex($buffer{0});
	$data = array();	
	$pdata = array();
	$raw = array();
	$offset = 1;
	if(!isset($pstruct[$pid])){
		write_packet("ff", array("message" => "Unknown packet ID ".$pid));
		if(arg("log", false) != false){
			$p = "==".time()."==> ERROR Unknown Packet $pid :".PHP_EOL;
			$p .= hexdump(substr($buffer,0,64), false, false, true);
			$p .= PHP_EOL . "--------------- (64 byte extract) ----------" .PHP_EOL .PHP_EOL;
			file_put_contents($path."packets.log", $p, FILE_APPEND);
		}
		return array("pid" => "ff", "message" => "Unknown packet ID ".$pid);
	}
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
				$raw[] = $r = substr($buffer,$offset,$len * 2);
				$pdata[] = no_endian($r);
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
				$pdata[] = convert("d", $r);
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
				}
				$pdata[] = $r;
				break;
			case "chunkArray":
				$len = max(0,$pdata[6]);
				$first = false;
				while(strlen($r)<$len){ //Sometimes low-bandwidth servers made client a crash
					if($first == true){
						buffer();
						global $buffer;
					}
					$first = true;						
					$r = substr($buffer, $offset, $len);
				}
				if(arg("dump", false) != false){
					$pdata[] = $r;
				}
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
			case "slotArray":
			case "slotData":
				$scount = $type == "slotData" ? 1:$pdata[count($pdata)-1];
				for($i=0;$i<$scount;++$i){
					$id = read_short(substr($buffer,$offset,2));
					$offset += 2;
					if($id != -1){						
						$count = read_byte($buffer{$offset});
						$offset += 1;
						$meta = read_short(substr($buffer,$offset,2));
						$offset += 2;
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
							 0x13A, 0x13B, 0x13C, 0x13D					
						);
						if(in_array($id, $enchantable_items)){
							$len = read_short(substr($buffer,$offset,2));
							$offset += 2;
							$arr = substr($buffer, $offset, $len);
							$offset += $len;
						}
					}
				}
				$pdata[] = "";
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
	if(arg("log", false) != false){
		$p = "==".time()."==> RECEIVED Packet $pid, lenght $offset :".PHP_EOL;
		$p .= hexdump(substr($buffer,0,$offset), false, false, true);
		$p .= PHP_EOL . "--------------- END ----------" .PHP_EOL .PHP_EOL;
		file_put_contents($path."packets.log", $p, FILE_APPEND);
	}
	
	$buffer = substr($buffer, $offset); // Clear packet
	
	switch($pid){
		case "00":
			$data["ka_id"] = $pdata[0];
			break;
		case "01": //Login
			$data["eid"] = $pdata[0];
			$data["seed"] = $pdata[2];
			$data["mode"] = $pdata[3];
			$data["dimension"] = $pdata[4];
			$data["difficulty"] = $pdata[5];
			$data["height"] = $pdata[6];
			$data["max_players"] = $pdata[7];
			/*if(arg("dump",false)){
				print_r();
			}*/
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
		case "33":
			if(arg("dump",false) != false){
				$x = $pdata[0];
				$y = $pdata[1];
				$z = $pdata[2];
				$len = $pdata[6];
				$chunk = gzinflate(substr($pdata[7],2));
				$fname = "world/region/r.". ($x >> 5).".".($z >> 5).".mcr";
				@mkdir($path."world/region/",0777,true);
				file_put_contents($path.$fname,$chunk);
			}
			break;
		case "ff":
			$data["message"] = $pdata[0];
			break;
	}
	$data["pid"] = $pid;
	$data["raw"] = $raw;
	return $data;
}

function write_packet($pid,$data){
	global $sock, $path, $protocol;
	switch($pid){
		case "00":
			$packet = "\x00".
			($protocol <= 14 ? "":$data["raw"][0]);
			break;
		case "01":	//Login	
			$packet = "\x01".
			"\x00\x00".pack("n*",$data["version"]).
			string_pack(strlen($data["username"])).
			endian($data["username"]).
			str_repeat("\x00", (version_compare($data["version"], "1.7.3", "<=") == true ? 9:16));
			break;
		
		case "02": //Handshake
			$packet = "\x02".
			string_pack(strlen($data["username"])).endian($data["username"]);
			break;
		case "03":
			$packet = "\x03".
			string_pack(strlen($data["message"])).endian($data["message"]);
			break;
		case "0a":
			$packet = "\x0a".
			($data["ground"] == true ? "\x01":"\x00");
			break;			
		case "ff":
			$packet = "\xff".
			string_pack(strlen($data["message"])).endian($data["message"]);
			break;
	}
	if(arg("log", false) != false){
		$len = strlen($packet);
		$p = "==".time()."==> SENT Packet $pid, lenght $len:".PHP_EOL;
		$p .= hexdump($packet, false, false, true);
		$p .= PHP_EOL . "--------------- END ----------" .PHP_EOL .PHP_EOL;
		file_put_contents($path."packets.log", $p, FILE_APPEND);
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

function endian_convert($a){
	return ((($a&0x000000FF)<<24) | ((($a&0x0000FF00)>>8)<<16) | ((($a&0x00FF0000)>>16)<<8) | (($a&0xFF000000)>>24));
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
	global $buffer, $sock;
	if(!isset($buffer)){
		$buffer = "";
	}
	if(strlen($buffer) < (MAX_BUFFER_BYTES / 8)){
		if(strlen($buffer) < 128){
			socket_set_block($sock);
		}else{
			socket_set_nonblock($sock);
		}
		$read = @socket_read($sock,2048, PHP_BINARY_READ);
		if($read != false and $read != ""){
			$buffer .= $read;
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

?>
<?php
set_time_limit(0);
if(!defined('CLIENT_LOADED')){
	$path = dirname(__FILE__)."/";
	include("functions.php");
	include("pstruct.php");
	//include("nbt.class.php");
	//ini_set("display_errors", 0);
	define('CLIENT_LOADED', true);
	define("MAX_BUFFER_BYTES", 1024 * 1024 * 16);
	ini_set("memory_limit", "32M");
}

$versions = array(
	"1.0.1" => 22,
	"1.0.0" => 22,
	"1.8.1" => 17,
	"1.8" => 17,
	"1.7.3" => 14,
	"1.7.2" => 14,
	"1.7_01" => 14,
	"1.7" => 14,
	"1.6.6" => 12,
	"1.6" => 12,
);

$lastver = "1.0.1";



if(arg("help", false) !== false){

echo <<<USAGE

<?php Minecraft PHP Client ?>
\tby shoghicp
Usage: php {$argv[0]} [parameters]

Parameters:
\tserver => Server to connect, default "127.0.0.1"
\tport => Port to connect, default "25565"
\tversion => Version of server, default "$lastver"
\tusername => username to use in server and minecraft.net (if PREMIUM), default "Player"
\tpassword => password to use in minecraft.net, if PREMIUM
\tsecure => use HTTPS to connect to minecraft.net
\tdump => dump map chunks (experimental! [no crash])
\tlog => write a log in packets.log and console.log
\tping => ping (packet 0xFE) a server, and returns info
\thide => hides elements here from console, separated by a comma (sign, chat, nspawn, state, position)
\tcrazyness => moves around doing things (moves head) (values: mad, normal)
\towner => set owner (follow, commands)
\tfollow => follow owner

Example:
php {$argv[0]} --server=127.0.0.1 --username=shoghicp --version=1.8.1 --hide=sign,chat

USAGE;
die();
}

$server		= arg("server", "127.0.0.1");
$port		= arg("port", "25565");
$username	= arg("username", "Player");
$password	= arg("password", "");
$secure		= arg("secure", false);
$version	= arg("version", $lastver);
if(arg("log", false) != false){
	file_put_contents($path."packets.log", "");
	file_put_contents($path."console.log", "");
}
$protocol = $versions[$version];
$colorchar = "\xa7";

include("pstruct_modifier.php");


$logged_in = false;
$connected = true;

$login = array("last_version" => "", "download_ticket" => "", "username" => $username, "session_id" => "");

echo <<<INFO

<?php Minecraft PHP Client ?>
\tby shoghicp


INFO;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_connect($sock, $server, $port);
socket_set_block($sock);
socket_set_option($sock, SOL_SOCKET, SO_KEEPALIVE, 1);
socket_set_option($sock, SOL_TCP, TCP_NODELAY, 1);

if(arg("ping", false) != false){
	console("[+] Pinging ".$server." ...");
	write_packet("fe");
	buffer();
	$packet = parse_packet();
	if($packet["pid"] == "ff"){
		$info = explode($colorchar,$packet["message"]);
		console("[*] Name: ".$info[0]);
		console("[*] Online players: ".$info[1]);
		console("[*] Max players: ".$info[2]);
	}
	die();
}


$hide = explode(",", arg("hide", ""));

/*

------------------ AUTH START -----------------

*/

console("[*] Sending Handshake");
write_packet("02", array(
	"username" => $username,
));

buffer();
$packet = parse_packet(); //Wait for Server Handshake

if($packet["server_id"] != "-" and $packet["server_id"] != "+"){
	console("[*] Server is Premium (SID: ".$packet["server_id"].")");
	if($packet["server_id"] == "" or strpos($packet["server_id"], "&") !== false){
		console("[!] NAME SPOOF DETECTED");
	}
	if($secure !== false){
		$proto = "https";
		console("[+] Using secure HTTPS connection");
	}else{
		$proto = "http";
	}
	
	$response = curl_get($proto."://login.minecraft.net/?user=".$username."&password=".$password."&version=12");
	switch($response){
		case 'Bad Login':
			console("[-] Bad login");
			die();
			break;
		case "Old Version":
			console("[-] Old Version");
			die();
			break;
		default:
			$content = explode(":",$response);
			if(!is_array($content)){
				console("[-] Unknown Login Error: \"".$response."\"");
				die();
			}
			$login["last_version"] = $content[0];
			$login["download_ticket"] = $content[1];
			$login["username"] = $content[2];
			$username = $content[2];
			$login["session_id"] = $content[3];
			console("[+] Logged into minecraft.net". PHP_EOL);
			break;
	}
	$res = curl_get("http://session.minecraft.net/game/joinserver.jsp?user=".$username."&sessionId=".$login["session_id"]."&serverId=".$packet["server_id"]); //User check
	if($res != "OK"){
		console("[-] Error in User Check: \"".$res."\"");
		die();
	}
}else{
	console("[*] Server is not Premium");
}
console("[*] Sending Login Request");

write_packet("01",array(
	"version" => $protocol,
	"username" => $username,
));

socket_set_nonblock($sock);

/*
---------- AUTH FINALIZED --------------
*/

$position_packet = false;
$next = 0;
$start = $next;
$moving = 0;
$ginfo = array(
"eid" => 0,
"seed" => 0,
"dimension" => 0,
"difficulty" => 0,
"mode" => 0,
"height" => 128,
"crouch" => 0,
"jump" => 0,
"health" => 20,
"owner" => array(
	"name" => arg("owner", "shoghicp"),
	"eid" => 0,
	"x" => 0,
	"y" => 0,
	"z" => 0,
),
);


while($sock){
	$time = microtime(true);
	buffer();
	if(strlen($buffer) > 0){	
		$packet = parse_packet();
		switch($packet["pid"]){
			case "00":
				write_packet("00",$packet);
				break;			
			case "01":
				console("[+] Login Request accepted");
				console("[*] EID: ".$packet["eid"]);
				console("[*] Seed: ".$packet["seed"]);
				$ginfo["eid"] = $packet["eid"];
				$ginfo["seed"] = $packet["seed"];
				$ginfo["dimension"] = $packet["dimension"];
				$ginfo["difficulty"] = $packet["difficulty"];
				$ginfo["mode"] = $packet["mode"];
				$ginfo["height"] = $packet["height"];
				if($protocol>=17){
					console("[*] Gamemode: ".($packet["mode"]==0 ? "survival":"creative"));
					console("[*] Max players: ".$packet["max_players"]);
				}
				$logged_in = true;
				break;
			case "03":
				if(!in_array("chat",$hide)){
					$len = strlen($packet["message"]);
					//Clean packet for console
					for($i=0;$i<$len;++$i){
						if($packet["message"]{$i} == $colorchar){
							$packet["message"]{$i} = "\xff";
							$packet["message"]{$i+1} = "\xff";
						}
					}
					$packet["message"] = str_replace("\xff", "", $packet["message"]);
					if($packet["message"]{0} == "["){
						$sender = substr($packet["message"],1,strpos($packet["message"], "->")-2);
						$packet["message"] = substr($packet["message"],strpos($packet["message"], "]")+2);
						if($sender == $ginfo["owner"]["name"]){
							if($packet["message"] == "follow"){
								$arguments["commands"]["follow"] = $arguments["commands"]["follow"] == false ? true:false;
							}else{
								write_packet("03", array(
									"message" => $packet["message"],
								));
							}
							console("me: ".$packet["message"]);
						}else{
							console("[+] $sender: ".$packet["message"]);
						}
					}else{
						console($packet["message"]);
					}
				}
				break;
				
			case "04":
				console("[*] Time: ".((intval($packet['time']/1000+6) % 24)).':'.str_pad(intval(($packet['time']/1000-floor($packet['time']/1000))*60),2,"0",STR_PAD_LEFT).', '.(($packet['time'] > 23100 or $packet['time'] < 12900) ? "day":"night")."   \r", false, false);
				break;
			case "08":
				if($ginfo["health"] != $packet["health"]){
					console("[*] Health: ".$packet["health"]);
				}
				$ginfo["health"] = $packet["health"];
				if($ginfo["health"]<=0){
					write_packet("09", $ginfo);
					console("[-] Death and respawn");
				}
				break;
			case "0d":
				if(!in_array("position",$hide)){
					console("[+] Got position: (".$packet["x"].",".$packet["y"].",".$packet["z"].")");
				}
				if($moving == 0 or $moving >= 2){
					write_packet("0d",$packet);
					$position_packet = $packet;
					$moving = 0;
				}
				
				
				break;
			case "14":
				if(!in_array("nspawn",$hide)){
					console("[+] Player \"".$packet["name"]."\" (EID: ".$packet["eid"].") spawned at (".$packet["x"].",".$packet["y"].",".$packet["z"].")");
				}
				if($packet["name"] == $ginfo["owner"]["name"]){
					$ginfo["owner"]["eid"] = $packet["eid"];
					$ginfo["owner"]["x"] = $packet["x"];
					$ginfo["owner"]["y"] = $packet["y"];
					$ginfo["owner"]["z"] = $packet["z"];
				}
				break;
			case "1f":
			case "21":
				if($packet["eid"] == $ginfo["owner"]["eid"]){
					$ginfo["owner"]["x"] += $packet["dX"];
					$ginfo["owner"]["y"] += $packet["dY"];
					$ginfo["owner"]["z"] += $packet["dZ"];				
				}
				break;
			case "22":
				if($packet["eid"] == $ginfo["owner"]["eid"]){
					$ginfo["owner"]["x"] = $packet["x"];
					$ginfo["owner"]["y"] = $packet["y"];
					$ginfo["owner"]["z"] = $packet["z"];				
				}			
				break;
			case "46";
				if(!in_array("state",$hide)){
					switch($packet["reason"]){
						case 0:
							$m = "Invalid bed";
							break;
						case 1:
							$m = "Started raining";
							break;
						case 2:
							$m = "Ended raining";
							break;
						case 3:
							$m = "Gamemode changed: ".($packet["mode"]==0 ? "survival":"creative");
							break;
						case 4:
							$m = "Entered credits";
							break;
					}
					console("[*] ".$m);
				}
				break;
			case "82":
				if(!in_array("sign",$hide)){
					console("[*] Sign at (".$packet["x"].",".$packet["y"].",".$packet["z"].")".PHP_EOL.implode(PHP_EOL,$packet["text"]));
				}
				break;
			case "ff":
				console("[-] Kicked from server, \"".$packet["message"]."\"");
				socket_close($sock);
				die();
				break;
		}
	}
	
	$do = false;
	/*if($next <= $time and $position_packet !== false){
		write_packet("0a", array(
			"ground" => true,
		));
		$do = true;
	}*/
	if($next <= $time and $position_packet !== false){
		if(arg("follow",false) != false and $ginfo["owner"]["eid"] > 0){
			$xD = abs($position_packet["x"] - $ginfo["owner"]["x"]);
			$yD = abs($position_packet["y"] - $ginfo["owner"]["y"]);
			$zD = abs($position_packet["z"] - $ginfo["owner"]["z"]);
			if(sqrt(pow($xD,2) + pow($zD,2)) <= 16 and sqrt(pow($xD,2) + pow($zD,2)) >= 2 and $yD <= 3 and $moving <= 2){
				if($ginfo["jump"] == 1){
					$ginfo["jump"] = 0;
				}else{
					$ginfo["jump"] = 1;
				}
				$position_packet["x"] += ($position_packet["x"] - $ginfo["owner"]["x"]>0 ? -0.1:0.1);
				$position_packet["y"] = (sqrt(pow($xD,2) + pow($zD,2)) <= 3) ? $ginfo["owner"]["y"]:$position_packet["y"]/* + $ginfo["jump"]*/;
				$position_packet["stance"] = $position_packet["y"] + 1.6;
				$position_packet["yaw"] = -rad2deg(atan(($position_packet["x"] - $ginfo["owner"]["x"])/($position_packet["z"] - $ginfo["owner"]["z"])));
				$position_packet["pitch"] = mt_rand(-10,10);
				$position_packet["z"] += ($position_packet["z"] - $ginfo["owner"]["z"]>0 ? -0.1:0.1);
				//$position_packet["ground"] = $ginfo["jump"] == 1 ? false:true;
				write_packet("0d", $position_packet);
			}else{
				$moving = 0;
			}
			/*if(sqrt(pow($xD,2) + pow($zD,2)) <= 4){
				write_packet("07", array(
					"eid" => $ginfo["eid"],
					"target" => $ginfo["owner"]["eid"],
					"left" => true,
				));
			}*/
		}
		if($moving == 0){
			if(arg("crazyness","normal") == "mad"){
				if(mt_rand(0,100)<=80){
					if(mt_rand(0,100)<=40){
						$position_packet["x"] += mt_rand(-30,30)/210;
						$position_packet["z"] += mt_rand(-30,30)/210;
					}
					$position_packet["yaw"] = mt_rand(-360,360);
					$position_packet["pitch"] = mt_rand(-360,360);
					write_packet("0d", $position_packet);
					write_packet("12", array(
						"eid" => $ginfo["eid"],
						"animation" => 1,
					));
				}else{			
					write_packet("13", array(
						"eid" => $ginfo["eid"],
						"action" => ($crouch == false ? 1:2),
					));
					$crouch = $crouch == true ? false:true;
				}
			}else{
				if(mt_rand(0,100)<=20){
					$position_packet["x"] += mt_rand(-30,30)/210;
					$position_packet["z"] += mt_rand(-30,30)/210;
				}
				$position_packet["yaw"] += mt_rand(-25,25);
				$position_packet["yaw"] %= 360;
				$position_packet["pitch"] += mt_rand(-10,10);
				$position_packet["pitch"] %= 55;
				write_packet("0d", $position_packet);
			}
		}
		$do = true;
	}
	/*if($start+120<=$time){
		write_packet("ff", array("message" => "Bot auto-disconnect"));
		console("[-] Kicked from server, \"Bot auto-disconnect\"");
		socket_close($sock);
		die();
	}*/
	if($do){
		$next = $time+0.05;
	}
	
}
socket_close($sock);
?>
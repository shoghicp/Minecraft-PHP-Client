<?php
set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);
ini_set("display_errors", 1);

if(!defined('CLIENT_LOADED')){
	$path = dirname(__FILE__)."/";
	set_include_path($path);
	include_once("functions.php");
	include_once("packets.php");
	include_once("command.php");
	include_once("dynmap.php");
	include_once("chunk.php");
	//include("nbt.class.php");
	//ini_set("display_errors", 0);
	define("VERSION", "0.6.2 Alpha");
	define("MAX_BUFFER_BYTES", 1024 * 1024 * 16);
	define("RESTART_TIME", 60 * 60); //1h
	ini_set("memory_limit", "128M");


$versions = array(
	"1.2.5" => 29,
	"1.2.4" => 29,
	"1.2.3" => 28,
	"1.2.2" => 28,
	"1.2.1" => 28,
	"1.2.0" => 28,
	"1.1.0" => 23,
	"1.0.1" => 22,
	"1.0.0" => 22,
	"b1.8.1" => 17,
	"b1.8" => 17,
	"b1.7.3" => 14,
	"b1.7.2" => 14,
	"b1.7_01" => 14,
	"b1.7" => 14,
	"b1.6.6" => 12,
	"b1.6" => 12,
);

$lastver = "1.2.5";



if(arg("help", false) !== false){

echo <<<USAGE

<?php Minecraft PHP Client ?>
\tby shoghicp
Usage: php {$argv[0]} [parameters]

Parameters:
\tserver => Server to connect, default "127.0.0.1"
\tport => Port to connect, default "25565"
\tversion => Version of server, default "$lastver"
\tprotocol
\tusername => username to use in server and minecraft.net (if PREMIUM), default "Player"
\tpassword => password to use in minecraft.net, if PREMIUM
\tsecure => use HTTPS to connect to minecraft.net
\tdump => dump map chunks (experimental! [no crash])
\tlog => write a log in packets.log, console.log and raw.log, or if you specify an option, only one
\tping => ping (packet 0xFE) a server, and returns info
\thide => hides elements here from console, separated by a comma (sign, chat, nspawn, state, position)
\tcrazyness => moves around doing things (moves head) (values: mad, normal)
\towner => set owner (follow, commands)
\tonly-food => only accept food as inventory items (default false)
\tdynmap => enables dynmap if a port is given (default false)

Example:
php {$argv[0]} --server=127.0.0.1 --username=shoghicp --version=b1.8.1 --hide=sign,chat

USAGE;
die();
}
$server		= arg("server", "127.0.0.1");
$port		= arg("port", "25565");
$username	= arg("username", "Player");
$password	= arg("password", "");
$secure		= arg("secure", false);
$version	= arg("version", $lastver);
$protocol	= intval(arg("protocol", $versions[$lastver]));

if(arg("log", false) != false){
	if(arg("log", false) == "console"){
		file_put_contents($path."console.log", "");	
	}elseif(arg("log", false) == "packets"){
		file_put_contents($path."packets.log", "");
	}elseif(arg("log", false) == "raw"){
		file_put_contents($path."raw_recv.log", "");
		file_put_contents($path."raw_sent.log", "");
	}else{
		file_put_contents($path."packets.log", "");
		file_put_contents($path."console.log", "");	
	}
}
if($version != $lastver){
	$protocol = $versions[$version];
}

$colorchar = "\xa7";

}
include("materials.php");
include("pstruct.php");
include("pstruct_modifier.php");

if(!defined("CLIENT_LOADED")){

$logged_in = false;
$connected = true;

$login = array("last_version" => "", "download_ticket" => "", "username" => $username, "session_id" => "");

echo <<<INFO

<?php Minecraft PHP Client ?>
\tby shoghicp


INFO;

}

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
	"server" => $server.":".$port,
));

buffer();
$packet = parse_packet(); //Wait for Server Handshake

if($packet["server_id"] != "-" and $packet["server_id"] != "+"){
	console("[*] Server is Premium (SID: ".$packet["server_id"].")");
	if($packet["server_id"] == "" or strpos($packet["server_id"], "&") !== false){
		console("[!] NAME SPOOF DETECTED");
	}
	if(!defined("CLIENT_LOADED")){
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
define('CLIENT_LOADED', true);
/*
---------- AUTH FINALIZED --------------
*/

$position_packet = false;
$next = 0;
$start = $next;
$moving = 0;
$chunks = array();
$tchunk = array();
$ginfo = array(
	"eid" => 0,
	"seed" => 0,
	"dimension" => 0,
	"difficulty" => 0,
	"level_type" => "DEFAULT",
	"mode" => 0,
	"height" => 128,
	"crouch" => 0,
	"jump" => 0,
	"health" => 20,
	"food" => 20,
	"timer" => array(
		"restart" => time() + RESTART_TIME,
	),
	"state" => array(),
	"time" => 0,
	"follow" => 0,
	"fly" => false,
	"inventory" => array(),
	"attack" => false,
	"aura" => false,
	"owner" => array(
		"name" => arg("owner", "shoghicp"),
	),
);
$entities = array();
$players = array();
$permissions = array(
	$ginfo["owner"]["name"] => 3,
	$username => 3,
	"susoboiro" => 2,
	"LoscoJones" => 2,
	"milodescorpio" => 2,
	"Duendek86" => 2,
	"kopron" => 2,
	"creik" => 2,
	"Sir_Pinkata",
);
$recorder = array("mode" => "", "name" => "", "positions" => "");
$restart = false;


while($sock and $restart == false){
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
				$ginfo["level_type"] = $packet["level_type"];
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
				$res = Packet03Chat($packet);
				if(!in_array("chat",$hide) and $res != false){
					console($res);
				}
				break;
				
			case "04":
				$packet['time'] %= 24000;
				$ginfo["time"] = $packet['time'];
				if(!in_array("time",$hide)){
					console("[*] Time: ".((intval($packet['time']/1000+6) % 24)).':'.str_pad(intval(($packet['time']/1000-floor($packet['time']/1000))*60),2,"0",STR_PAD_LEFT).', '.(($packet['time'] > 23100 or $packet['time'] < 12900) ? "day":"night")."   \r", false, false);
				}
				break;
			case "08":
				if($ginfo["health"] != $packet["health"] or $ginfo["food"] != $packet["food"]){
					console("[*] Health: ".$packet["health"].", Food: ". $packet["food"]);
				}
				$ginfo["health"] = $packet["health"];
				$ginfo["food"] = $protocol <= 14 ? $packet["health"]:$packet["food"];
				if($ginfo["health"]<=0){
					write_packet("09", $ginfo);
					$messages = array(
						"Nooo!!!",
						"Por que??",
						"Solo hice lo que me pedian!",
						"Noooouuu!",			
					);
					Message($messages[count($messages)-1]);
					console("[-] Death and respawn");
				}
				break;
			case "0d":
				if(!in_array("position",$hide)){
					console("[+] Got position: (".$packet["x"].",".$packet["y"].",".$packet["z"].")");
				}
				/*if(!$position_packet and arg("spout", false) != false){
					write_packet("12", array(
						"eid" => -42,
						"animation" => 1,
					));
				}*/
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
				if(isset($permissions[$packet["name"]]) and $permissions[$packet["name"]] > 1){
					if($ginfo["owner"]["name"] == $packet["name"]){
						$ginfo["owner"]["eid"] = $packet["eid"];
					}
					privateMessage("Hola ".$packet["name"].". Eres ".($permissions[$packet["name"]] == 2 ? "moderador":"administrador")." de ".$username, $packet["name"]);
				}
				$players[$packet["name"]] = $packet["eid"];			
			case "17":
			case "18":
				$entities[$packet["eid"]]["type"] = $packet["type"];
				$entities[$packet["eid"]]["x"] = $packet["x"];
				$entities[$packet["eid"]]["y"] = $packet["y"];
				$entities[$packet["eid"]]["z"] = $packet["z"];
				break;
			case "1d":
				unset($entities[$packet["eid"]]);
				if(in_array($packet["eid"], $players)){
					foreach($players as $name => $eid){
						if($eid == $packet["eid"]){
							unset($players[$name]);
							break;
						}
					}
				}
				break;
			case "1f":
			case "21":
				$entities[$packet["eid"]]["x"] += $packet["dX"];
				$entities[$packet["eid"]]["y"] += $packet["dY"];
				$entities[$packet["eid"]]["z"] += $packet["dZ"];				

				break;
			case "22":
				$entities[$packet["eid"]]["x"] = $packet["x"];
				$entities[$packet["eid"]]["y"] = $packet["y"];
				$entities[$packet["eid"]]["z"] = $packet["z"];					
				break;
			case "33":
				if($protocol <= 23){
					if($packet["xS"] == 15 and $packet["yS"] == 127 and $packet["zS"] == 15){
						chunk_add($packet["chunk"], $packet["x"], $packet["z"]);
						chunk_clean($packet["x"], $packet["z"]);
					}
				}
				break;
			case "35":
				if($protocol <= 23){
					chunk_edit_block($packet["x"],$packet["y"],$packet["z"],$packet["type"]);
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
			case "67":
				if($packet["wid"] == 0){
					$ginfo["inventory"][$packet["slot"]] = $packet["sdata"][0];
				}
				break;
				
			case "68":
				if($packet["wid"] == 0){
					foreach($packet["sdata"] as $i => $slot){
						$ginfo["inventory"][$i] = $slot;
					}
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
	
	if($next <= $time and $time%32==0 and intval(arg("dynmap", false)) > 0){
		foreach(DynMapCoords() as $player){
			if(!isset($players[$player["name"]]) or $players[$player["name"]] == md5($player["name"])){
				$players[$player["name"]] = md5($player["name"]);
				$entities[md5($player["name"])] = $player;
			}
		}
	}
	if($next <= $time){
		$input = file_get_contents("chat.input");file_put_contents("chat.input", "");
		foreach(explode("\n", $input) as $in){
			$in = trim($in);
			if($in == ""){
				continue;
			}
			Message($in);
			console("[+] INPUT: ".$in);
		}
	}
	if($ginfo["aura"] == true and $position_packet !== false){
		$att = false;
		foreach($entities as $eid => $ent){
			$xD = abs($position_packet["x"] - $ent["x"]);
			$yD = abs($position_packet["y"] - $ent["y"]);
			$zD = abs($position_packet["z"] - $ent["z"]);
			if(pow(pow($xD,2) + pow($yD,2) + pow($zD,2),1/3) <= 7){
				write_packet("07", array(
					"eid" => $ginfo["eid"],
					"target" => $eid,
					"left" => true,
				));
				$att = true;
			}
		}
		if($att == true){
			write_packet("12", array(
				"eid" => $ginfo["eid"],
				"animation" => 1,
			));
		}
	}
	
	if($next <= $time and $position_packet !== false){
		if($recorder["mode"] == "record"){
			$recorder["positions"][] = $entities[$ginfo["follow"]];
		}
		if($recorder["mode"] == "play"){
			if(isset($recorder["positions"][$recorder["name"]])){
				$recorder["positions"][$recorder["name"]]["stance"] = $recorder["positions"][$recorder["name"]]["y"] + 1.6;
				$recorder["positions"][$recorder["name"]]["ground"] = true;
				write_packet("0b", $recorder["positions"][$recorder["name"]]);
				++$recorder["name"];
			}else{
				$recorder["mode"] = "";
			}
		}else{
			if($ginfo["follow"] > 0){
				$xD = abs($position_packet["x"] - $entities[$ginfo["follow"]]["x"]);
				$yD = abs($position_packet["y"] - $entities[$ginfo["follow"]]["y"]);
				$zD = abs($position_packet["z"] - $entities[$ginfo["follow"]]["z"]);
				if($ginfo["attack"] == true and pow(pow($xD,2) + pow($yD,2) + pow($zD,2),1/3) <= 7){
					write_packet("07", array(
						"eid" => $ginfo["eid"],
						"target" => $ginfo["follow"],
						"left" => true,
					));
					write_packet("12", array(
						"eid" => $ginfo["eid"],
						"animation" => 1,
					));
				}
				if(sqrt(pow($xD,2) + pow($zD,2)) <= 32 and sqrt(pow($xD,2) + pow($zD,2)) >= 2 and $moving <= 2){
					if($ginfo["jump"] > 0){
						$ginfo["jump"] = -1;
					}else{
						$ginfo["jump"] = 1;
					}
					$position_packet["x"] += ($position_packet["x"] - $entities[$ginfo["follow"]]["x"]>0 ? -0.25:0.25);
					if($ginfo["fly"] == false){
						$position_packet["y"] = $position_packet["y"] + $ginfo["jump"];
					}else{
						$position_packet["y"] += ($position_packet["y"] - $entities[$ginfo["follow"]]["y"]>0 ? -0.25:0.25);
					}
					$position_packet["stance"] = $position_packet["y"] + 1.6;
					$position_packet["yaw"] = -rad2deg(atan(($position_packet["x"] - $entities[$ginfo["follow"]]["x"])/($position_packet["z"] - $entities[$ginfo["follow"]]["z"])));
					$position_packet["pitch"] = mt_rand(-10,10);
					$position_packet["z"] += ($position_packet["z"] - $entities[$ginfo["follow"]]["z"]>0 ? -0.25:0.25);
					$position_packet["ground"] = ($ginfo["jump"] > 0 and $ginfo["fly"] == false) ? false:true;
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
				}elseif(arg("crazyness","normal") == "exorcist"){
						$position_packet["yaw"] = 0;
						$position_packet["pitch"] += 6;
						$position_packet["pitch"] %= 360;
						write_packet("0d", $position_packet);
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
		}
		$do = true;
	}
	/*if($start+120<=$time){
		write_packet("ff", array("message" => "Bot auto-disconnect"));
		console("[-] Kicked from server, \"Bot auto-disconnect\"");
		socket_close($sock);
		die();
	}*/
	if($ginfo["timer"]["food"]<=$time and $position_packet !== false){
		$ginfo["timer"]["food"] = $time+4;
		$eat = false;
		for($i=36;$i<=44;++$i){
			$slot = $ginfo["inventory"][$i];
			if(isset($food[$slot[0]]) == true and ($ginfo["food"] + $food[$slot[0]]) <= 20){
				write_packet("10",array("slot" => $i-36));
				write_packet("0f", array("x" => -1, "y" => -1, "z" => -1, "direction" => -1, "slot" => array(-1)));
				$ginfo["food"] = 20;
				$eat = true;
				break;
			}elseif(!isset($food[$slot[0]]) and arg("only-food", false) == true){
				for($a=0;$a<min(3,$slot[1]);++$a){
					write_packet("10",array("slot" => $i-36));				
					write_packet("0e", array("status" => 4, "x" => 0, "y" => 0, "z" => 0, "face" => 0));
				}
			}
		}			
		if($ginfo["timer"]["sayfood"]<=$time and $eat == false and $ginfo["food"] <= 12){
			$ginfo["timer"]["sayfood"] = $time+60;
			$messages = array(
				"Necesito comida!",
				"Comida!!!",
				"Me muero de hambre!",
				"No tengo comida!",			
			);
			Message($messages[count($messages)-1]);
		}
	}
	if($do){
		$next = $time+0.05;
	}
	usleep(10);
}
socket_close($sock);
if($restart == true){
	console("[+] Restarting...");
	$buffer = "";
	sleep(8);
	include("client.php");
}
die();
?>

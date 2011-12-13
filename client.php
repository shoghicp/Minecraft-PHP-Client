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
/**/ Minecraft PHP Client /**/
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
\tlog => write a log in packets.log

Example:
php {$argv[0]} --server=127.0.0.1 --username=shoghicp --version=1.8.1

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
$colorchar = "\xc2\xa7";

include("pstruct_modifier.php");


$logged_in = false;

$login = array("last_version" => "", "download_ticket" => "", "username" => $username, "session_id" => "");

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_connect($sock, $server, $port);
socket_set_block($sock);

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

$spawn_packet = false;
$next = time();
$walk = 0.5;
$start = $next;
while($sock){
	$time = time();
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
				if($protocol>=17){
					console("[*] Mode: ".($packet["mode"]==0 ? "survival":"creative"));
					console("[*] Max players: ".$packet["max_players"]);
				}
				$logged_in = true;
				break;
			case "03":
				$len = strlen($packet["message"]);
				//Clean packet for console
				for($i=0;$i<$len;++$i){
					if($packet["message"]{$i} == "\xa7"){
						$packet["message"]{$i} = "\xff";
						$packet["message"]{$i+1} = "\xff";
					}
				}
				$packet["message"] = str_replace("\xff", "", $packet["message"]);
				console($packet["message"]);
				break;
				
			case "04":
				console("[*] Time: ".((intval($packet['time']/1000+6) % 24)).':'.str_pad(intval(($packet['time']/1000-floor($packet['time']/1000))*60),2,"0",STR_PAD_LEFT).', '.(($packet['time'] > 23100 or $packet['time'] < 12900) ? "day":"night")."   \r", false, false);
				break;
			case "0d":
				console("[+] Got spawn position: (".$packet["x"].",".$packet["y"].",".$packet["z"].")");
				$spawn_packet = $packet;
				write_packet("0d",$packet);
				break;
			case "14":
				console("[+] Player \"".$packet["name"]."\" (EID: ".$packet["eid"].") spawned");
				break;
			case "ff":
				console("[-] Kicked from server, \"".$packet["message"]."\"");
				socket_close($sock);
				die();
				break;
		}
	}
	
	$do = false;
	if($next <= $time and $time%8==0){
		write_packet("0a", array(
			"ground" => true,
		));
		$do = true;
	}
	if($next <= $time and $time%4==0 and $spawn_packet !== false){
		$walk = -$walk;
		$spawn_packet["x"] += $walk;
		write_packet("0b", $spawn_packet);
		$do = true;
	}
	/*if($start+120<=$time){
		write_packet("ff", array("message" => "Bot auto-disconnect"));
		console("[-] Kicked from server, \"Bot auto-disconnect\"");
		socket_close($sock);
		die();
	}*/
	if($do){
		$next = $time+1;
	}
	
}
socket_close($sock);
?>
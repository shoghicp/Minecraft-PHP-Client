<?php
set_time_limit(0);
if(!defined('CLIENT_LOADED')){
	$path = dirname(__FILE__)."/";
	include("functions.php");
	include("pstruct.php");
	//include("nbt.class.php");
	//ini_set("display_errors", 0);
	define('CLIENT_LOADED', true);
}

$versions = array(
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
$versions["2.0.0"] = $versions["1.0.0"];
$lastver = "1.0.0";

define("MAX_BUFFER_BYTES", 1024 * 1024 * 16);
ini_set("memory_limit", "32M");

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
echo "[*] Sending Handshake". PHP_EOL;
write_packet("02", array(
	"username" => $username,
));

buffer();
$packet = parse_packet(); //Wait for Server Handshake

if($packet["server_id"] != "-" and $packet["server_id"] != "+"){
	echo "[*] Server is Premium (SID: ".$packet["server_id"].")" . PHP_EOL;
	if($packet["server_id"] == "" or strpos($packet["server_id"], "&") !== false){
		echo "[!] NAME SPOOF DETECTED" . PHP_EOL;
	}
	if($secure !== false){
		$proto = "https";
		echo "[+] Using secure HTTPS connection" . PHP_EOL;
	}else{
		$proto = "http";
	}
	
	$response = curl_get($proto."://login.minecraft.net/?user=".$username."&password=".$password."&version=12");
	switch($response){
		case 'Bad Login':
			die("[-] Bad login");
			break;
		case "Old Version":
			die("[-] Old Version");
			break;
		default:
			$content = explode(":",$response);
			if(!is_array($content)){
				die("[-] Unknown Login Error: \"".$response."\"");
			}
			$login["last_version"] = $content[0];
			$login["download_ticket"] = $content[1];
			$login["username"] = $content[2];
			$username = $content[2];
			$login["session_id"] = $content[3];
			echo "[+] Logged into minecraft.net". PHP_EOL;
			break;
	}
	$res = curl_get("http://session.minecraft.net/game/joinserver.jsp?user=".$username."&sessionId=".$login["session_id"]."&serverId=".$packet["server_id"]); //User check
	if($res != "OK"){
		die("[-] Error in User Check: \"".$res."\"");
	}
}else{
	echo "[*] Server is not Premium" . PHP_EOL;
}
echo "[*] Sending Login Request". PHP_EOL;

write_packet("01",array(
	"version" => $protocol,
	"username" => $username,
));

socket_set_nonblock($sock);

$next = time();
while(1){
	$time = time();
	buffer();
	if(strlen($buffer) > 0){	
		$packet = parse_packet();
		switch($packet["pid"]){
			case "00":
				write_packet("00",$packet);
				break;			
			case "01":
				echo "[+] Login Request accepted".PHP_EOL;
				echo "[*] EID: ".$packet["eid"]. PHP_EOL;
				echo "[*] Seed: ".$packet["seed"]. PHP_EOL;
				if($protocol>=17){
					echo "[*] Mode: ".($packet["mode"]==0 ? "survival":"creative"). PHP_EOL;
					echo "[*] Max players: ".$packet["max_players"]. PHP_EOL;
				}
				$logged_in = true;
				break;
			case "03":
				if(strpos($packet["message"], "There are") === false and strpos($packet["message"], "Moderador:") === false and strpos($packet["message"], "Veterano:") === false and strpos($packet["message"], "Novato:") === false and strpos($packet["message"], "Vip:") === false){
					echo "[+] ".$packet["message"] .PHP_EOL;
				}
				break;
				
			case "04":
				echo "[*] Time: ".((intval($packet['time']/1000+6) % 24)).':'.str_pad(intval(($packet['time']/1000-floor($packet['time']/1000))*60),2,"0",STR_PAD_LEFT).', '.(($packet['time'] > 23100 or $packet['time'] < 12900) ? "day":"night")."   \r";
				break;
			case "14":
				echo "[+] Player \"".$packet["name"]."\" (EID: ".$packet["eid"].") spawned" . PHP_EOL;
				break;
			case "ff":
				echo "[-] Kicked from server, \"".$packet["message"]."\"". PHP_EOL;
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
	if($do){
		$next = $time+1;
	}
	
}
socket_close($sock);
?>
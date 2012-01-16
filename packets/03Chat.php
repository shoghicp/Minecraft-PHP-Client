<?php
	function Packet03Chat($packet){
		global $colorchar, $arguments, $ginfo;
		$len = strlen($packet["message"]);
		//Clean packet for console
		for($i=0;$i<$len;++$i){
			if($packet["message"]{$i} == $colorchar){
				$packet["message"]{$i} = "\xff";
				$packet["message"]{$i+1} = "\xff";
			}
		}
		$packet["message"] = str_replace(array("\xff", "[Server]", "[Server]"), array("", "<Server>"), $packet["message"]);
		$channel = "";
		if(strpos($packet["message"], "]<") !== false){
			$packet["message"] = explode("]<", substr($packet["message"],1));
			$channel = $packet["message"][0];
			$packet["message"] = "<".$packet["message"][1];
		}
		$sender = false;
		$me = false;
		if($packet["message"]{0} == "["){
			$sender = explode(".", substr($packet["message"],1,strpos($packet["message"], "->")-2));
			$sender = isset($sender[1]) ? $sender[1]:$sender[0];
			$packet["message"] = substr($packet["message"],strpos($packet["message"], "]")+2);
		}elseif(strpos($packet["message"], " whispers ") !== false){
			$sender = substr($packet["message"],0,strpos($packet["message"], " "));
			$packet["message"] = substr($packet["message"],strpos($packet["message"], " whispers ")+10);
		}elseif(stripos($packet["message"], "bot") !== false){
			
			
			
			if($packet["message"]{0} == "<"){
				$mess = substr($packet["message"],strpos($packet["message"], "> ")+2);
				$sender = explode(".", substr($packet["message"],1,strpos($packet["message"], "> ")-1));
			}else{
				$sender = explode(".", substr($packet["message"],0,strpos($packet["message"], ": ")));	
				$mess = substr($packet["message"],strpos($packet["message"], ": ")+2);		
			}
			$sender = isset($sender[1]) ? $sender[1]:$sender[0];
			
			if(strtolower(substr($mess,0,3)) != "bot"){
				$sender = false;
			}else{
				$me = true;
				$packet["message"] = substr($mess, ($mess{4} == " " ? 5:4));
			}
		}

		$mp = false;
		if(($sender !== false and $sender != "me" and $sender != "yo") or $me == true){
			$mp = true;
			$value = explode(" ", $packet["message"]);
			$command = strtolower($value[0]);
			unset($value[0]);
			$value = implode(" ",$value);
			if(!command($command, $value, $sender)){
				include("chat_help.php");
			}
			return "[$sender --> me] ".$packet["message"];
		}else{
			if($packet["message"]{0} == "<"){
				$sender = explode(".", substr($packet["message"],1,strpos($packet["message"], "> ")-1));
			}else{
				$sender = explode(".", substr($packet["message"],0,strpos($packet["message"], ": ")-1));			
			}
			$sender = isset($sender[1]) ? $sender[1]:$sender[0];
			include("chat_help.php");
			return $packet["message"];
		}	
	}
	
	function privateMessage($message, $target){
		foreach(explode("\n", wordwrap($message,100-strlen("/tell $target "), "\n")) as $mess){
			write_packet("03", array(
				"message" => "/tell $target $mess",
			));
		}
		console("[me --> $target] ".$message);
	}
	
	function Message($message){
		foreach(explode("\n", wordwrap($message,100, "\n")) as $mess){
			write_packet("03", array(
				"message" => $mess,
			));	
		}
	}
?>
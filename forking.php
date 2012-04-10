<?php

$sockets = array();
socket_create_pair(strtoupper(substr(PHP_OS,0,3)) == "WIN" ? AF_INET:AF_UNIX, SOCK_STREAM, 0, $sockets);
$parent_sock = $sockets[0];
$child_sock = $sockets[1];
unset($sockets);
$pid = pcntl_fork();
if($pid == -1){
	console("[-] Failed to fork");
	define("THREADED", false);
}elseif($pid){ //Parent
	socket_close($child_sock);
	define("THREADED", true);
	define("CHILD", false);
}else{ //Child
	define("CHILD", true);
    socket_close($parent_sock);
    forking_runtime($child_sock);
    posix_kill(getmypid(),9);
}




function forking_runtime($IOsock){
	global $sock, $buffer, $chunks;
	socket_set_nonblock($IOsock);
	$last = time();
	while($sock){
		buffer();
		if(($last + 10) < time()){
			return;
		}
		/*if($read = socket_read($IOsock,4096,PHP_NORMAL_READ) === false){
			if(socket_last_error($IOsock) == 104){
				return;
			}
		}*/
		$action = trim($read);
		switch($action){
			case "buffer":
				$last = time();
				$len = strlen($buffer) > 4096 ? 4096:strlen($buffer);				
				socket_write($IOsock, urlencode(substr($buffer,0,$len))."\n");
				$buffer = substr($buffer,$len);
				break;
			case "chunk":
				$last = time();
				$packet = unserialize(urldecode(trim(socket_read($IOsock,4096*16,PHP_NORMAL_READ))));
				chunk_add($packet["chunk"], $packet["x"], $packet["z"]);
				chunk_clean($packet["x"], $packet["z"]);
				socket_write($IOsock, urlencode(serialize($chunks))."\n");
				break;
			case "chunk2":
				$last = time();
				$packet = unserialize(urldecode(trim(socket_read($IOsock,4096*16,PHP_NORMAL_READ))));
				chunk_edit_block($packet["x"],$packet["y"],$packet["z"],$packet["type"]);
				socket_write($IOsock, urlencode(serialize($chunks))."\n");
				break;
			case "die":
				return;
				break;
		}
	}

}

function fork_chunk($packet, $pid){
	global $parent_sock, $chunks;
	socket_write($parent_sock,"chunk".($pid == 33 ? "":"2")."\n".urlencode(serialize($packet))."\n");
	$chunks = unserialize(urldecode(trim(socket_read($parent_sock,4096,PHP_NORMAL_READ))));
}

function fork_buffer(){
	global $parent_sock, $buffer, $connected;
	
	socket_write($parent_sock,"buffer\n");
	if(strlen($buffer) < 128 and $connected){
		socket_set_block($parent_sock);
	}else{
		socket_set_nonblock($parent_sock);
	}
	return urldecode(trim(socket_read($parent_sock,4096,PHP_NORMAL_READ)));
}

?>

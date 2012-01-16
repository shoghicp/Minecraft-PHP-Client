<?php
$path = dirname(__FILE__);
set_include_path($path);
function cli_read(){
	$handle = fopen ("php://stdin","r");
	$line = trim(fgets($handle));
	fclose($handle);
	return $line;
}
while(1){
	$say = cli_read().PHP_EOL; //leer por consola lo que dices
	echo "<me> $say";
	file_put_contents("chat.input",$say,FILE_APPEND);
}
?>